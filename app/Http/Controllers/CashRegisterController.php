<?php

namespace App\Http\Controllers;

use App\Models\CashRegister;
use App\Models\CashMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
}