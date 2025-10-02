<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
// app/Http/Controllers/ReportsController.php (adicionar este método)
use App\Models\Product;
use Illuminate\Support\Facades\Response;

class ReportsController extends Controller
{
     public function sales(Request $request)
    {
        $from  = $request->filled('from') ? Carbon::parse($request->get('from')) : now()->startOfMonth();
        $to    = $request->filled('to')   ? Carbon::parse($request->get('to'))   : now();
        $group = $request->get('group','dia'); // dia|mes|vendedor|pagamento

        $base = Order::where('status','paid')
            ->whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()]);

        if ($group === 'mes') {
            $rows = $base->selectRaw("DATE_FORMAT(created_at,'%Y-%m') as label, COUNT(*) as count, SUM(total) as total")
                         ->groupBy('label')->orderBy('label')->get();
        } elseif ($group === 'vendedor') {
            $rows = $base->selectRaw("COALESCE((SELECT name FROM users WHERE users.id = orders.user_id), '—') as label, COUNT(*) as count, SUM(total) as total")
                         ->groupBy('label')->orderByDesc('total')->get();
        } elseif ($group === 'pagamento') {
            $rows = $base->selectRaw("COALESCE(payment_method,'—') as label, COUNT(*) as count, SUM(total) as total")
                         ->groupBy('label')->orderByDesc('total')->get();
        } else { // dia
            $rows = $base->selectRaw("DATE(created_at) as label, COUNT(*) as count, SUM(total) as total")
                         ->groupBy('label')->orderBy('label')->get()
                         ->map(function($r){ $r->label = \Carbon\Carbon::parse($r->label)->format('d/m/Y'); return $r; });
        }

        $sumTotal = $rows->sum('total');

        // Export CSV simples
        if ($request->get('export') === 'csv') {
            $csv = "label,total,count\n";
            foreach ($rows as $r) {
                $csv .= "{$r->label},".number_format($r->total,2,'.','').",".($r->count ?? 0)."\n";
            }
            return response($csv,200,[
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="relatorio_vendas.csv"'
            ]);
        }

        return view('reports.sales', compact('from','to','group','rows','sumTotal'));
    }

    public function stock(Request $request)
{
    $q = trim($request->get('q',''));
    $filter = $request->get('filter','');
    $categoryId = $request->get('category_id');

    $items = Product::query()
        ->with('category')
        ->when($q, function($qry) use ($q){
            $qry->where(function($w) use ($q){
                $w->where('name','like',"%{$q}%")
                  ->orWhere('sku','like',"%{$q}%")
                  ->orWhere('ean','like',"%{$q}%")
                  ->orWhere('internal_barcode','like',"%{$q}%");
            });
        })
        ->when($categoryId, fn($qry)=>$qry->where('category_id',$categoryId))
        ->when($filter==='inativos', fn($qry)=>$qry->where('is_active',false))
        ->when($filter==='zero', fn($qry)=>$qry->where('stock','<=',0))
        ->when($filter==='neg', fn($qry)=>$qry->where('stock','<',0))
        ->when($filter==='min', fn($qry)=>$qry->whereColumn('stock','<','min_stock'))
        ->orderBy('name')
        ->paginate(30)
        ->appends($request->query());

    // Export CSV
    if ($request->get('export') === 'csv') {
        $all = Product::query()
            ->with('category')
            ->when($q, function($qry) use ($q){
                $qry->where(function($w) use ($q){
                    $w->where('name','like',"%{$q}%")
                      ->orWhere('sku','like',"%{$q}%")
                      ->orWhere('ean','like',"%{$q}%")
                      ->orWhere('internal_barcode','like',"%{$q}%");
                });
            })
            ->when($categoryId, fn($qry)=>$qry->where('category_id',$categoryId))
            ->when($filter==='inativos', fn($qry)=>$qry->where('is_active',false))
            ->when($filter==='zero', fn($qry)=>$qry->where('stock','<=',0))
            ->when($filter==='neg', fn($qry)=>$qry->where('stock','<',0))
            ->when($filter==='min', fn($qry)=>$qry->whereColumn('stock','<','min_stock'))
            ->orderBy('name')
            ->get();

        $csv = "name,sku,category,stock,min_stock,price,total_value,is_active\n";
        foreach ($all as $p) {
            $csv .= sprintf(
                "\"%s\",%s,\"%s\",%d,%d,%.2f,%.2f,%s\n",
                str_replace('"','""',$p->name),
                $p->sku,
                optional($p->category)->name,
                (int)$p->stock,
                (int)$p->min_stock,
                (float)$p->price,
                (float)($p->price * max(0,$p->stock)),
                $p->is_active ? '1':'0'
            );
        }
        return Response::make($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="estoque.csv"'
        ]);
    }

    return view('reports.stock', compact('items'));
}



}
