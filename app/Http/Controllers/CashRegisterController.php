<?php

namespace App\Http\Controllers;

use App\Models\CashRegister;
use App\Models\CashMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CashRegisterController extends Controller
{
    public function index() {
        $open = CashRegister::open()->latest('id')->first();
        $history = CashRegister::latest('id')->limit(10)->get();
        return view('cash.index', compact('open','history'));
    }

    public function open(Request $r) {
        $data = $r->validate(['opening_amount'=>['nullable','numeric','min:0']]);
        abort_if(CashRegister::open()->exists(), 422, 'Já existe um caixa aberto.');
        $reg = CashRegister::create([
            'opening_amount' => $data['opening_amount'] ?? 0,
            'status' => 'open',
            'user_opened_id' => optional($r->user())->id,
            'initial_amount' => 'nullable|numeric|min:0',
            'open_note'      => 'nullable|string|max:2000',
        ]);

            $reg = \App\Models\CashRegister::create([
        'opened_at'     => now(),
        'opened_by'     => $r->user()->id ?? null,
        'initial_amount'=> $data['initial_amount'] ?? 0,
        'open_note'     => $data['open_note'] ?? null,
        'status'        => 'open',
        ]);
        return redirect()->route('cash.index')->with('ok','Caixa aberto.');
    }

    public function close(Request $r) {
        $open = CashRegister::open()->latest('id')->firstOrFail();
        $total = $open->movements()->sum(DB::raw("CASE WHEN type='IN' THEN amount ELSE -amount END"));
        $closing = $open->opening_amount + $total;
        $open->update([
            'status'=>'closed',
            'closing_amount'=>$closing,
            'closed_at'=>now(),
            'user_closed_id'=>optional($r->user())->id,
            'close_note' => 'nullable|string|max:2000',
        ]);

         $reg = \App\Models\CashRegister::where('status','open')->latest('id')->firstOrFail();

    // ... calcule totais/lançamentos/pedidos, etc ...
    $reg->update([
        'closed_at'  => now(),
        'closed_by'  => $r->user()->id ?? null,
        'close_note' => $data['close_note'] ?? null,
        'status'     => 'closed',
    ]);

        return redirect()->route('cash.index')->with('ok','Caixa fechado.');
    }

    // lançamentos manuais (suprimento/sangria)
    public function movement(Request $r) {
        $data = $r->validate([
            'type'=>['required','in:IN,OUT'],
            'amount'=>['required','numeric','min:0.01'],
            'reason'=>['nullable','string','max:120'],
            'payment_method'=>['nullable','string','max:40'],
        ]);
        $open = CashRegister::open()->latest('id')->firstOrFail();
        $data['cash_register_id'] = $open->id;
        $data['user_id'] = optional($r->user())->id;
        CashMovement::create($data);
        return back()->with('ok','Lançamento registrado.');
    }

    public function closePreview(){
        $reg = CashRegister::open()->latest('id')->firstOrFail();

        $byMethod = CashMovement::where('cash_register_id',$reg->id)
            ->select('payment_method',
            DB::raw("SUM(CASE WHEN type='IN' THEN amount ELSE 0 END) as entradas"),
            DB::raw("SUM(CASE WHEN type='OUT' THEN amount ELSE 0 END) as saidas")
            )
            ->groupBy('payment_method')
            ->orderBy('payment_method')
            ->get();

        $sangrias = CashMovement::where('cash_register_id',$reg->id)
            ->where('type','OUT')
            ->where('reason','like','%Sangria%')
            ->sum('amount');

        $totalMov = CashMovement::where('cash_register_id',$reg->id)
            ->selectRaw("SUM(CASE WHEN type='IN' THEN amount ELSE -amount END) as total")
            ->value('total');

        $prevClosing = $reg->opening_amount + ($totalMov ?? 0);

        return view('cash.close', compact('reg','byMethod','sangrias','prevClosing'));
        }

            public function exportCsv(): StreamedResponse
{
            $reg = \App\Models\CashRegister::open()->latest('id')->firstOrFail();

            $byMethod = \App\Models\CashMovement::where('cash_register_id',$reg->id)
                ->select(
                    'payment_method',
                    DB::raw("SUM(CASE WHEN type='IN'  THEN amount ELSE 0 END) as entradas"),
                    DB::raw("SUM(CASE WHEN type='OUT' THEN amount ELSE 0 END) as saidas"),
                    DB::raw("SUM(CASE WHEN type='IN'  THEN amount ELSE -amount END) as saldo")
                )
                ->groupBy('payment_method')
                ->orderBy('payment_method')
                ->get();

            $sangrias = \App\Models\CashMovement::where('cash_register_id',$reg->id)
                ->where('type','OUT')->where('reason','like','%Sangria%')->sum('amount');

            $movTotal = \App\Models\CashMovement::where('cash_register_id',$reg->id)
                ->selectRaw("SUM(CASE WHEN type='IN' THEN amount ELSE -amount END) as total")
                ->value('total') ?? 0;

            $prevClosing = (float)$reg->opening_amount + (float)$movTotal;

            $filename = 'fechamento_caixa_'.$reg->id.'.csv';

            return response()->streamDownload(function() use ($reg,$byMethod,$sangrias,$prevClosing) {
                $out = fopen('php://output','w');
                // BOM p/ Excel
                fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
                $csv = fn($row) => fputcsv($out, $row, ';');

                $csv(['Fechamento do Caixa', '#'.$reg->id]);
                $csv(['Aberto em', optional($reg->opened_at)->format('d/m/Y H:i')]);
                $csv(['Fundo de troco', number_format($reg->opening_amount,2,',','.')]);
                $csv(['Sangrias', number_format($sangrias,2,',','.')]);
                $csv(['Previsão de fechamento', number_format($prevClosing,2,',','.')]);
                $csv(['']); // linha em branco
                $csv(['Método','Entradas','Saídas','Saldo']);

                foreach ($byMethod as $r) {
                    $csv([
                        $r->payment_method ? ucfirst($r->payment_method) : '—',
                        number_format($r->entradas,2,',','.'),
                        number_format($r->saidas,2,',','.'),
                        number_format($r->saldo,2,',','.')
                    ]);
                }
                fclose($out);
            }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
}


}