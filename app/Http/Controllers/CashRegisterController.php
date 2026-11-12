<?php

namespace App\Http\Controllers;

use App\Models\CashRegister;
use App\Models\CashMovement;
use App\Services\FinancialCoordinator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CashRegisterController extends Controller
{
    public function index(Request $request) {
        $openRegisters = CashRegister::open()->get();
        $ambiguous = $openRegisters->count() > 1;
        $open = $openRegisters->count() === 1 ? $openRegisters->first() : null;
        $history = CashRegister::latest('id')->limit(10)->get();
        $openingKey = $request->session()->get('cash.opening_key');
        if (! $open && ! $ambiguous && ! $openingKey) {
            $openingKey = (string) Str::uuid();
            $request->session()->put('cash.opening_key', $openingKey);
        }
        $closingKey = $open ? $this->operationKey($request, 'close', $open->id) : null;
        $movementKey = $open ? $this->operationKey($request, 'movement', $open->id) : null;
        return view('cash.index', compact('open', 'history', 'openingKey', 'ambiguous', 'openRegisters', 'closingKey', 'movementKey'));
    }

    public function open(Request $r, FinancialCoordinator $coordinator) {
        $result = $coordinator->open($r->user(), $r->only(['idempotency_key', 'opening_amount', 'open_notes']));
        if ($r->session()->get('cash.opening_key') === strtolower((string) $r->input('idempotency_key'))) {
            $r->session()->forget('cash.opening_key');
        }
        $message = $result['replayed']
            ? 'Abertura do caixa #'.$result['cash_register_id'].' já registrada.'
            : 'Caixa #'.$result['cash_register_id'].' aberto.';
        return redirect()->route('cash.index')->with('ok', $message);
    }

    public function close(Request $request, FinancialCoordinator $coordinator)
    {
        $result = $coordinator->close($request->user(), $request->only(['idempotency_key', 'cash_register_id', 'close_notes']));
        $this->forgetOperationKey($request, 'close', $result['cash_register_id']);
        $message = $result['replayed']
            ? 'Fechamento do caixa #'.$result['cash_register_id'].' já registrado.'
            : 'Caixa #'.$result['cash_register_id'].' fechado.';

        return redirect()->route('cash.index')->with('ok', $message);
    }

    public function movement(Request $request, FinancialCoordinator $coordinator)
    {
        $result = $coordinator->movement($request->user(), $request->only([
            'idempotency_key', 'cash_register_id', 'type', 'amount', 'reason', 'payment_method',
        ]));
        $this->forgetOperationKey($request, 'movement', $result['cash_register_id']);
        $message = $result['replayed']
            ? 'Lançamento #'.$result['cash_movement_id'].' já registrado.'
            : 'Lançamento #'.$result['cash_movement_id'].' registrado.';

        return redirect()->route('cash.index')->with('ok', $message);
    }

    private function operationKey(Request $request, string $action, int $registerId): string
    {
        $path = "cash.$action.$registerId.key";
        $key = $request->session()->get($path);
        if (! $key) {
            $key = (string) Str::uuid();
            $request->session()->put($path, $key);
        }

        return $key;
    }

    private function forgetOperationKey(Request $request, string $action, int $registerId): void
    {
        $path = "cash.$action.$registerId.key";
        if ($request->session()->get($path) === strtolower((string) $request->input('idempotency_key'))) {
            $request->session()->forget($path);
        }
    }

    public function closePreview(Request $request)
    {
        $summary = $this->closingSummary();
        $closingKey = $this->operationKey($request, 'close', $summary['reg']->id);

        return view('cash.close', $summary + compact('closingKey'));
    }

    public function closePrint()
    {
        return view('cash.close_print', $this->closingSummary());
    }

    private function closingSummary(): array
    {
        $registers = CashRegister::with(['openedBy', 'closedBy'])->open()->get();
        abort_if($registers->count() > 1, 409, 'Existem múltiplos caixas abertos. A operação depende da reconciliação.');
        $reg = $registers->first();
        abort_unless($reg, 404);

        $byMethod = CashMovement::where('cash_register_id', $reg->id)
            ->select(
                'payment_method',
                DB::raw("SUM(CASE WHEN type='IN' THEN amount ELSE 0 END) as entradas"),
                DB::raw("SUM(CASE WHEN type='OUT' THEN amount ELSE 0 END) as saidas"),
                DB::raw("SUM(CASE WHEN type='IN' THEN amount ELSE -amount END) as saldo")
            )
            ->groupBy('payment_method')
            ->orderBy('payment_method')
            ->get();

        $sangrias = CashMovement::where('cash_register_id', $reg->id)
            ->where('type', 'OUT')
            ->where('reason', 'like', '%Sangria%')
            ->sum('amount');

        $totalMov = CashMovement::where('cash_register_id', $reg->id)
            ->selectRaw("SUM(CASE WHEN type='IN' THEN amount ELSE -amount END) as total")
            ->value('total') ?? 0;

        // Vendas já estão nos movimentos. Somar pedidos novamente duplicaria entradas.
        $prevClosing = (float) $reg->opening_amount + (float) $totalMov;

        return compact('reg', 'byMethod', 'sangrias', 'prevClosing');
    }

    public function exportCsv(): StreamedResponse
    {
        [
            'reg' => $reg,
            'byMethod' => $byMethod,
            'sangrias' => $sangrias,
            'prevClosing' => $prevClosing,
        ] = $this->closingSummary();

        $filename = 'fechamento_caixa_'.$reg->id.'.csv';

        return response()->streamDownload(function () use ($reg, $byMethod, $sangrias, $prevClosing) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
            $csv = fn ($row) => fputcsv($out, $row, ';');

            $csv(['Fechamento do Caixa', '#'.$reg->id]);
            $csv(['Aberto em', optional($reg->opened_at)->format('d/m/Y H:i')]);
            $csv(['Fundo de troco', number_format($reg->opening_amount, 2, ',', '.')]);
            $csv(['Sangrias', number_format($sangrias, 2, ',', '.')]);
            $csv(['Previsão de fechamento', number_format($prevClosing, 2, ',', '.')]);
            $csv(['']);
            $csv(['Método', 'Entradas', 'Saídas', 'Saldo']);

            foreach ($byMethod as $row) {
                $csv([
                    $row->payment_method ? ucfirst($row->payment_method) : '—',
                    number_format($row->entradas, 2, ',', '.'),
                    number_format($row->saidas, 2, ',', '.'),
                    number_format($row->saldo, 2, ',', '.'),
                ]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
