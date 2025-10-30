<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
// app/Http/Controllers/ReportsController.php (adicionar este método)
use App\Models\Product;
use Illuminate\Support\Facades\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;



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

                // Helper: período
    protected function range(Request $r): array
    {
        $from = $r->get('from');
        $to   = $r->get('to');
        $from = $from ? Carbon::parse($from)->startOfDay() : Carbon::now()->startOfMonth();
        $to   = $to   ? Carbon::parse($to)->endOfDay()   : Carbon::now()->endOfDay();
        return [$from, $to];
    }

    /* ================== Vendas — Resumo diário ================== */
    public function salesSummary(Request $r)
    {
        [$from,$to] = $this->range($r);
        $pay   = $r->get('payment');   // opcional
        $rows = DB::table('orders')
            ->selectRaw('DATE(created_at) as day,
                         COUNT(*) as orders,
                         SUM(subtotal) as subtotal,
                         SUM(discount) as discount,
                         SUM(total) as total')
            ->whereBetween('created_at', [$from,$to])
            ->when($pay, fn($q)=>$q->where('payment_method',$pay))
            ->where('status','!=','canceled')
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        $totals = [
            'orders'   => $rows->sum('orders'),
            'subtotal' => $rows->sum('subtotal'),
            'discount' => $rows->sum('discount'),
            'total'    => $rows->sum('total'),
        ];

        return view('reports.sales_summary', compact('rows','from','to','pay','totals'));
    }

    public function salesSummaryCsv(Request $r): StreamedResponse
    {
        [$from,$to] = $this->range($r);
        $pay = $r->get('payment');

        $rows = DB::table('orders')
            ->selectRaw('DATE(created_at) as day,
                         COUNT(*) as orders,
                         SUM(subtotal) as subtotal,
                         SUM(discount) as discount,
                         SUM(total) as total')
            ->whereBetween('created_at', [$from,$to])
            ->when($pay, fn($q)=>$q->where('payment_method',$pay))
            ->where('status','!=','canceled')
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        return response()->streamDownload(function() use ($rows) {
            $out = fopen('php://output','w');
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($out, ['dia','pedidos','subtotal','desconto','total'], ';');
            foreach ($rows as $r) {
                fputcsv($out, [
                    $r->day, $r->orders,
                    number_format((float)$r->subtotal,2,',',''),
                    number_format((float)$r->discount,2,',',''),
                    number_format((float)$r->total,2,',','')
                ], ';');
            }
            fclose($out);
        }, 'vendas_resumo.csv', ['Content-Type'=>'text/csv; charset=UTF-8']);
    }

    /* ================== Vendas por Produto ================== */
    public function salesByProduct(Request $r)
    {
        [$from,$to] = $this->range($r);
        $q = trim($r->get('q',''));

        $rows = DB::table('order_items as oi')
            ->join('orders as o','o.id','=','oi.order_id')
            ->join('products as p','p.id','=','oi.product_id')
            ->leftJoin('categories as c','c.id','=','p.category_id')
            ->whereBetween('o.created_at', [$from,$to])
            ->where('o.status','!=','canceled')
            ->when($q, fn($w)=>$w->where(function($x) use($q){
                $x->where('p.name','like',"%$q%")->orWhere('p.sku','like',"%$q%");
            }))
            ->selectRaw('p.id, p.name, p.sku, c.name as category,
                         SUM(oi.qty) as qty,
                         SUM(oi.price*oi.qty - COALESCE(oi.discount,0)) as revenue')
            ->groupBy('p.id','p.name','p.sku','c.name')
            ->orderByDesc('revenue')
            ->limit(500)
            ->get();

        $totals = ['qty'=>$rows->sum('qty'), 'revenue'=>$rows->sum('revenue')];
        return view('reports.sales_products', compact('rows','from','to','q','totals'));
    }

    public function salesByProductCsv(Request $r): StreamedResponse
    {
        [$from,$to] = $this->range($r);
        $q = trim($r->get('q',''));

        $rows = DB::table('order_items as oi')
            ->join('orders as o','o.id','=','oi.order_id')
            ->join('products as p','p.id','=','oi.product_id')
            ->leftJoin('categories as c','c.id','=','p.category_id')
            ->whereBetween('o.created_at', [$from,$to])
            ->where('o.status','!=','canceled')
            ->when($q, fn($w)=>$w->where(function($x) use($q){
                $x->where('p.name','like',"%$q%")->orWhere('p.sku','like',"%$q%");
            }))
            ->selectRaw('p.name, p.sku, c.name as category,
                         SUM(oi.qty) as qty,
                         SUM(oi.price*oi.qty - COALESCE(oi.discount,0)) as revenue')
            ->groupBy('p.name','p.sku','c.name')
            ->orderByDesc('revenue')
            ->get();

        return response()->streamDownload(function() use ($rows) {
            $out = fopen('php://output','w');
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($out, ['produto','sku','categoria','qtd','faturamento'], ';');
            foreach ($rows as $r) {
                fputcsv($out, [$r->name, $r->sku, $r->category, (int)$r->qty,
                    number_format((float)$r->revenue,2,',','')], ';');
            }
            fclose($out);
        }, 'vendas_por_produto.csv', ['Content-Type'=>'text/csv; charset=UTF-8']);
    }

    /* ================== Vendas por Categoria ================== */
    public function salesByCategory(Request $r)
    {
        [$from,$to] = $this->range($r);

        $rows = DB::table('order_items as oi')
            ->join('orders as o','o.id','=','oi.order_id')
            ->join('products as p','p.id','=','oi.product_id')
            ->leftJoin('categories as c','c.id','=','p.category_id')
            ->whereBetween('o.created_at', [$from,$to])
            ->where('o.status','!=','canceled')
            ->selectRaw('COALESCE(c.name,"— Sem categoria —") as category,
                         SUM(oi.qty) as qty,
                         SUM(oi.price*oi.qty - COALESCE(oi.discount,0)) as revenue')
            ->groupBy('category')
            ->orderByDesc('revenue')
            ->get();

        $totals = ['qty'=>$rows->sum('qty'), 'revenue'=>$rows->sum('revenue')];
        return view('reports.sales_categories', compact('rows','from','to','totals'));
    }

    public function salesByCategoryCsv(Request $r): StreamedResponse
    {
        [$from,$to] = $this->range($r);

        $rows = DB::table('order_items as oi')
            ->join('orders as o','o.id','=','oi.order_id')
            ->join('products as p','p.id','=','oi.product_id')
            ->leftJoin('categories as c','c.id','=','p.category_id')
            ->whereBetween('o.created_at', [$from,$to])
            ->where('o.status','!=','canceled')
            ->selectRaw('COALESCE(c.name,"Sem categoria") as category,
                         SUM(oi.qty) as qty,
                         SUM(oi.price*oi.qty - COALESCE(oi.discount,0)) as revenue')
            ->groupBy('category')
            ->orderByDesc('revenue')
            ->get();

        return response()->streamDownload(function() use ($rows) {
            $out = fopen('php://output','w');
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($out, ['categoria','qtd','faturamento'], ';');
            foreach ($rows as $r) {
                fputcsv($out, [$r->category, (int)$r->qty,
                    number_format((float)$r->revenue,2,',','')], ';');
            }
            fclose($out);
        }, 'vendas_por_categoria.csv', ['Content-Type'=>'text/csv; charset=UTF-8']);
    }

    /* ================== Histórico de estoque (com filtros) ================== */
    public function stockHistory(Request $r)
    {
        [$from,$to] = $this->range($r);
        $productId = (int)$r->get('product_id', 0);
        $type      = $r->get('type'); // in|out|null

        $moves = DB::table('stock_movements as m')
            ->join('products as p','p.id','=','m.product_id')
            ->when($productId, fn($q)=>$q->where('m.product_id',$productId))
            ->when($type, fn($q)=>$q->where('m.type',$type))
            ->whereBetween('m.created_at', [$from,$to])
            ->selectRaw('m.*, p.name as product_name, p.sku')
            ->orderByDesc('m.id')
            ->paginate(50)
            ->appends($r->query());

        // totais do período
        $totIn  = DB::table('stock_movements')->whereBetween('created_at',[$from,$to])
                    ->when($productId, fn($q)=>$q->where('product_id',$productId))
                    ->sum(DB::raw("CASE WHEN type='in' THEN qty ELSE 0 END"));
        $totOut = DB::table('stock_movements')->whereBetween('created_at',[$from,$to])
                    ->when($productId, fn($q)=>$q->where('product_id',$productId))
                    ->sum(DB::raw("CASE WHEN type='out' THEN qty ELSE 0 END"));

        return view('reports.stock_history', compact('moves','from','to','productId','type','totIn','totOut'));
    }

    public function stockHistoryCsv(Request $r): StreamedResponse
    {
        [$from,$to] = $this->range($r);
        $productId = (int)$r->get('product_id', 0);
        $type      = $r->get('type');

        $rows = DB::table('stock_movements as m')
            ->join('products as p','p.id','=','m.product_id')
            ->when($productId, fn($q)=>$q->where('m.product_id',$productId))
            ->when($type, fn($q)=>$q->where('m.type',$type))
            ->whereBetween('m.created_at', [$from,$to])
            ->selectRaw('m.created_at, p.name as product, p.sku, m.type, m.qty, m.unit_price, m.reason, m.reason_code, m.user_id')
            ->orderBy('m.id')
            ->get();

        return response()->streamDownload(function() use ($rows) {
            $out = fopen('php://output','w');
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($out, ['data','produto','sku','tipo','qtd','preco_unit','motivo','cod_motivo','user_id'], ';');
            foreach ($rows as $r) {
                fputcsv($out, [
                    \Carbon\Carbon::parse($r->created_at)->format('d/m/Y H:i'),
                    $r->product, $r->sku, $r->type, (int)$r->qty,
                    number_format((float)$r->unit_price,2,',',''),
                    $r->reason, $r->reason_code, $r->user_id
                ], ';');
            }
            fclose($out);
        }, 'historico_estoque.csv', ['Content-Type'=>'text/csv; charset=UTF-8']);
    }
}


