<?php

namespace App\Services;

use App\Models\CashRegister;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use PDOException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

class FinancialCoordinator
{
    /**
     * Os demais escritores financeiros serão integrados nas fases 4.2 e 4.3.
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

                return $operation();
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
        $parts = explode('.', (string) ($data['opening_amount'] ?? '0'));
        $amount = (ltrim($parts[0], '+0') ?: '0').'.'.str_pad($parts[1] ?? '', 2, '0');
        $notes = trim($data['open_notes'] ?? '');
        $notes = $notes === '' ? null : $notes;
        $hash = hash('sha256', json_encode([
            'opening_amount' => $amount,
            'open_notes' => $notes,
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        return $this->coordinate(function () use ($actor, $key, $amount, $notes, $hash) {
            // Recuperar primeiro permite repetir a operação mesmo após fechamento
            // ou alteração posterior do estado, sem consultar ou alterar esse estado.
            $previous = DB::table('financial_operations')->where('idempotency_key', $key)->first();
            if ($previous !== null) {
                if ($previous->operation !== 'cash.open'
                    || (int) $previous->user_id !== (int) $actor->id
                    || ! hash_equals($previous->request_hash, $hash)) {
                    throw new ConflictHttpException('Chave de idempotência já utilizada com outra identidade ou conteúdo.');
                }

                return ['cash_register_id' => (int) $previous->cash_register_id, 'replayed' => true];
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

            DB::table('financial_operations')->insert([
                'idempotency_key' => $key,
                'operation' => 'cash.open',
                'user_id' => $actor->id,
                'request_hash' => $hash,
                'cash_register_id' => $id,
                'created_at' => $now,
            ]);

            return ['cash_register_id' => (int) $id, 'replayed' => false];
        });
    }
}
