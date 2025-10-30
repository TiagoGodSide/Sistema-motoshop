<?php
namespace App\Http\Controllers;

use App\Models\ServiceOrder;
use App\Models\ServiceOrderItem;
use Illuminate\Http\Request;

class ServiceOrderController extends Controller
{
    public function index(Request $r)
    {
        $status = $r->get('status');
        $q = trim($r->get('q',''));
        $rows = ServiceOrder::query()
            ->when($status, fn($q2)=>$q2->where('status',$status))
            ->when($q, fn($w)=>$w->where(function($x) use($q){
                $x->where('number','like',"%$q%")
                  ->orWhere('vehicle','like',"%$q%")
                  ->orWhere('plate','like',"%$q%");
            }))
            ->latest('id')->paginate(20)->appends($r->query());
        return view('os.index', compact('rows','status','q'));
    }

    public function create()
    {
        $os = new ServiceOrder(['status'=>'opened']);
        return view('os.create', compact('os'));
    }

    public function store(Request $r)
    {$data = $r->validate([
        // se tiver customer_id, mantenha:
        'customer_id' => 'nullable|exists:customers,id',

        // 👇 torne obrigatório o que você quer exigir
        'vehicle'     => 'required|string|max:120',   // antes era nullable
        'plate'       => 'required|string|max:20',    // se não quiser obrigatória, troque para 'nullable'
        'due_date'    => 'nullable|date',
        'notes'       => 'nullable|string|max:3000',

        // status/discount se existirem
        'status'      => 'nullable|in:opened,approved,in_service,ready,delivered,canceled',
        'discount'    => 'nullable|numeric|min:0',
    ], [
        'vehicle.required' => 'Informe o veículo/modelo.',
        'plate.required'   => 'Informe a placa.',
    ]);

    $data['user_id'] = optional($r->user())->id;
    $data['status'] = $data['status'] ?? 'opened';

    $os = \App\Models\ServiceOrder::create($data);

    return redirect()->route('os.edit', $os)->with('ok', 'OS criada.');
    }

    public function edit(ServiceOrder $os)
    {
        $os->load('items');
        return view('os.edit', compact('os'));
    }

    public function update(Request $r, ServiceOrder $os)
    {
       $data = $r->validate([
        'vehicle'  => 'required|string|max:120',
        'plate'    => 'required|string|max:20', // ou 'nullable|string|max:20'
        'due_date' => 'nullable|date',
        'notes'    => 'nullable|string|max:3000',
        'status'   => 'nullable|in:opened,approved,in_service,ready,delivered,canceled',
        'discount' => 'nullable|numeric|min:0',
    ], [
        'vehicle.required' => 'Informe o veículo/modelo.',
        'plate.required'   => 'Informe a placa.',
    ]);

    $os->update($data);
    $os->recalcTotals();

    return back()->with('ok', 'OS atualizada.');
    }

    public function addItem(Request $r, ServiceOrder $os)
    {
        $data = $r->validate([
            'type'=>'required|in:part,labor',
            'product_id'=>'nullable|exists:products,id',
            'description'=>'required|string|max:180',
            'qty'=>'required|integer|min:1',
            'unit_price'=>'required|numeric|min:0',
            'discount'=>'nullable|numeric|min:0',
        ]);
        $data['service_order_id'] = $os->id;
        ServiceOrderItem::create($data);
        return back()->with('ok','Item adicionado.');
    }

    public function removeItem(ServiceOrder $os, ServiceOrderItem $item)
    {
        abort_unless($item->service_order_id === $os->id, 404);
        $item->delete();
        return back()->with('ok','Item removido.');
    }

    // Converter OS aprovada em venda (rascunho/PDV)
    public function toPos(ServiceOrder $os)
    {
        // aqui você pode redirecionar para o PDV com os itens da OS na sessão
        // Exemplo simples: salvar na sessão e redirecionar
        $items = $os->items->map(fn($i)=>[
            'product_id'=>$i->product_id,
            'description'=>$i->description,
            'qty'=>$i->qty,
            'price'=>$i->unit_price,
            'type'=>$i->type,
        ])->toArray();

        session(['pos.os_items' => $items, 'pos.os_id' => $os->id]);
        return redirect()->route('pos.index')->with('ok','Itens da OS enviados ao PDV.');
    }
}
