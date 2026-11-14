<?php

namespace App\Services;

use App\Models\CashRegister;
use App\Models\User;
use Closure;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use LogicException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use PDOException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

class FinancialCoordinator
{
    private ?object $contextToken = null;

    public function ownsContext(object $token): bool
    {
        return $this->contextToken === $token;
    }

    public function activeCash(FinancialContext $context, int $id): CashRegister
    {
        $context->assertActive();
        return $this->requireActiveRegister($id);
    }

    public function context(?DateTimeImmutable $instant = null): FinancialContext
    {
        if ($this->contextToken === null || DB::connection()->transactionLevel() < 1) {
            throw new LogicException('Contexto financeiro solicitado fora da coordenação.');
        }
        return new FinancialContext($this, $this->contextToken, DB::connection(), $instant ?? CarbonImmutable::now('UTC'));
    }

    /** New order contracts own the outer transaction; coordinate() keeps its existing contract. */
    public function orderTransaction(Closure $operation): mixed
    {
        if (DB::connection()->transactionLevel() !== 0) {
            throw new LogicException('O novo contrato exige uma transação externa própria.');
        }
        $instant = null;
        return $this->coordinate(function () use ($operation, &$instant) {
            $instant ??= CarbonImmutable::now('UTC');
            return $operation($this->context($instant));
        });
    }
    /**
     * PDV e pedidos serão integrados na fase 4.3.
     * A linha permanente permite coordenar também quando nenhum caixa existe.
     */
    public function coordinate(Closure $operation): mixed
    {
        try {
            return DB::transaction(function () use ($operation) {
                $lock = DB::table('financial_coordination')->where('id', 1)->lockForUpdate()->first();
                if ($lock === null) {
                    throw new ServiceUnavailableHttpException(1, 'Coordenação financeira indisponível. Tente novamente com a mesma chave.');
                }

                $previousToken = $this->contextToken;
                $this->contextToken ??= new \stdClass();
                try {
                    return $operation();
                } finally {
                    $this->contextToken = $previousToken;
                }
            }, 3);
        } catch (PDOException $exception) {
            report($exception);

            throw new ServiceUnavailableHttpException(1, 'Não foi possível concluir a operação financeira. Tente novamente com a mesma chave.', $exception);
        }
    }

    /** @return array{cash_register_id: int, replayed: bool} */
    public function open(User $actor, array $input): array
    {
        $data = Validator::make($input, [
            'idempotency_key' => ['required', 'uuid'],
            'opening_amount' => ['nullable', 'numeric', 'min:0', 'max:99999999.99', 'regex:/^\+?\d+(?:\.\d{1,2})?$/'],
            'open_notes' => ['nullable', 'string', 'max:2000'],
        ])->validate();

        $key = strtolower($data['idempotency_key']);
        $amount = $this->normalizeAmount((string) ($data['opening_amount'] ?? '0'));
        $notes = trim($data['open_notes'] ?? '');
        $notes = $notes === '' ? null : $notes;
        $hash = hash('sha256', json_encode([
            'opening_amount' => $amount,
            'open_notes' => $notes,
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        return $this->coordinate(function () use ($actor, $key, $amount, $notes, $hash) {
            // Recuperar primeiro permite repetir a operação mesmo após fechamento
            // ou alteração posterior do estado, sem consultar ou alterar esse estado.
            $store = app(OperationReceiptStore::class);
            $previous = $store->recover($this->context(), $actor, $key, 'cash.open', $hash);
            if ($previous !== null) {
                return $previous + ['replayed' => true];
            }

            $openIds = CashRegister::open()->lockForUpdate()->pluck('id');
            if ($openIds->count() > 1) {
                throw new ConflictHttpException('Existem múltiplos caixas abertos. A abertura depende da reconciliação.');
            }
            if ($openIds->isNotEmpty()) {
                throw new ConflictHttpException('Já existe um caixa aberto.');
            }

            // O banco aplica seu fuso aos TIMESTAMP. CURRENT_TIMESTAMP representa
            // o instante correto sem interpretar uma string UTC como horário local.
            // A uniformização da conexão e da apresentação fica para a fase 4.4.
            $now = DB::raw('CURRENT_TIMESTAMP');
            $id = CashRegister::query()->insertGetId([
                'status' => 'open',
                'opening_amount' => $amount,
                'user_opened_id' => $actor->id,
                'open_notes' => $notes,
                'opened_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $financialId = DB::table('financial_operations')->insertGetId([
                'idempotency_key' => $key,
                'operation' => 'cash.open',
                'user_id' => $actor->id,
                'request_hash' => $hash,
                'cash_register_id' => $id,
                'created_at' => $now,
            ]);

            $result = ['cash_register_id' => (int) $id];
            $store->append($this->context(), $actor, $key, 'cash.open', $hash, null, (int) $financialId, $result);
            return $result + ['replayed' => false];
        });
    }

    public function close(User $actor, array $input): array
    {
        $data = Validator::make($input, [
            'idempotency_key' => ['required', 'uuid'],
            'cash_register_id' => ['required', 'integer', 'min:1'],
            'close_notes' => ['nullable', 'string', 'max:2000'],
        ])->validate();
        $payload = [
            'cash_register_id' => (int) $data['cash_register_id'],
            'close_notes' => $this->normalizeText($data['close_notes'] ?? null),
        ];
        $key = strtolower($data['idempotency_key']);

        return $this->coordinate(function () use ($actor, $key, $payload) {
            $previous = $this->recover($actor, $key, 'cash.close', $payload);
            if ($previous !== null) {
                return $previous;
            }
            $register = $this->requireActiveRegister($payload['cash_register_id']);
            $balance = $this->toCents((string) $register->opening_amount);
            // Leitura atual com bloqueio, inclusive sob REPEATABLE READ.
            $movements = $register->movements()->lockForUpdate()->get(['type', 'amount']);
            foreach ($movements as $movement) {
                $cents = $this->toCents((string) $movement->amount);
                $balance += $movement->type === 'IN' ? $cents : -$cents;
            }
            if (abs($balance) > 9999999999) {
                throw ValidationException::withMessages(['cash_register_id' => 'O saldo excede o limite monetário suportado.']);
            }
            $amount = $this->formatCents($balance);
            $now = DB::raw('CURRENT_TIMESTAMP');
            CashRegister::whereKey($register->id)->update([
                'status' => 'closed',
                'closing_amount' => $amount,
                'closed_at' => $now,
                'user_closed_id' => $actor->id,
                'close_notes' => $payload['close_notes'],
                'updated_at' => $now,
            ]);
            $result = ['cash_register_id' => $register->id, 'closing_amount' => $amount];
            $this->record($actor, $key, 'cash.close', $payload, $result);

            return $result + ['replayed' => false];
        });
    }

    public function movement(User $actor, array $input): array
    {
        $data = Validator::make($input, [
            'idempotency_key' => ['required', 'uuid'],
            'cash_register_id' => ['required', 'integer', 'min:1'],
            'type' => ['required', 'in:IN,OUT'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999.99', 'regex:/^\+?\d+(?:\.\d{1,2})?$/'],
            'reason' => ['nullable', 'string', 'max:120'],
            'payment_method' => ['nullable', 'string', 'max:40'],
        ])->validate();
        $payload = [
            'cash_register_id' => (int) $data['cash_register_id'],
            'type' => $data['type'],
            'amount' => $this->normalizeAmount((string) $data['amount']),
            'reason' => $this->normalizeText($data['reason'] ?? null),
            'payment_method' => $this->normalizeText($data['payment_method'] ?? null),
        ];
        $key = strtolower($data['idempotency_key']);

        return $this->coordinate(function () use ($actor, $key, $payload) {
            $previous = $this->recover($actor, $key, 'cash.movement', $payload);
            if ($previous !== null) {
                return $previous;
            }
            $register = $this->requireActiveRegister($payload['cash_register_id']);
            $now = DB::raw('CURRENT_TIMESTAMP');
            $id = DB::table('cash_movements')->insertGetId($payload + [
                'user_id' => $actor->id,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $result = [
                'cash_register_id' => $register->id,
                'cash_movement_id' => (int) $id,
                'amount' => $payload['amount'],
                'type' => $payload['type'],
            ];
            $this->record($actor, $key, 'cash.movement', $payload, $result);

            return $result + ['replayed' => false];
        });
    }

    private function requireActiveRegister(int $id): CashRegister
    {
        $active = CashRegister::open()->lockForUpdate()->get();
        if ($active->count() > 1) {
            throw new ConflictHttpException('Existem múltiplos caixas abertos. A operação depende da reconciliação.');
        }
        if ($active->count() !== 1 || (int) $active->first()->id !== $id) {
            throw new ConflictHttpException('O caixa informado não é o único caixa ativo. Atualize a página.');
        }

        return $active->first();
    }

    private function recover(User $actor, string $key, string $operation, array $payload): ?array
    {
        $result = app(OperationReceiptStore::class)->recover($this->context(), $actor, $key, $operation, $this->requestHash($payload));
        return $result === null ? null : $result + ['replayed' => true];
    }

    private function record(User $actor, string $key, string $operation, array $payload, array $result): void
    {
        $id = DB::table('financial_operations')->insertGetId([
            'idempotency_key' => $key,
            'operation' => $operation,
            'user_id' => $actor->id,
            'request_hash' => $this->requestHash($payload),
            'cash_register_id' => $result['cash_register_id'],
            'cash_movement_id' => $result['cash_movement_id'] ?? null,
            'result' => json_encode($result, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'created_at' => DB::raw('CURRENT_TIMESTAMP'),
        ]);
        app(OperationReceiptStore::class)->append($this->context(), $actor, $key, $operation,
            $this->requestHash($payload), null, (int) $id, $result);
    }

    private function requestHash(array $payload): string
    {
        return hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    private function normalizeAmount(string $amount): string
    {
        $parts = explode('.', $amount);

        return (ltrim($parts[0], '+0') ?: '0').'.'.str_pad($parts[1] ?? '', 2, '0');
    }

    private function normalizeText(?string $text): ?string
    {
        $text = trim($text ?? '');

        return $text === '' ? null : $text;
    }

    private function toCents(string $amount): int
    {
        $negative = str_starts_with($amount, '-');
        $parts = explode('.', ltrim($amount, '+-'));
        $cents = (int) $parts[0] * 100 + (int) str_pad($parts[1] ?? '', 2, '0');

        return $negative ? -$cents : $cents;
    }

    private function formatCents(int $cents): string
    {
        return ($cents < 0 ? '-' : '').intdiv(abs($cents), 100).'.'.str_pad((string) (abs($cents) % 100), 2, '0', STR_PAD_LEFT);
    }
}
