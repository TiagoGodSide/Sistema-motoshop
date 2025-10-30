<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Customer;

class ReportController extends Controller
{
    public function customers(Request $r)
    {
        $q    = trim((string)$r->get('q',''));
        $from = $r->date('from');
        $to   = $r->date('to');

        $rows = Customer::query()
            ->leftJoin('orders','orders.customer_id','=','customers.id')
            ->when($from && $to, fn($w)=>$w->whereBetween('orders.created_at', [$from->startOfDay(), $to->endOfDay()]))
            ->when($q, function($w) use ($q) {
                $w->where(function($x) use ($q) {
                    $x->where('customers.name','like',"%$q%")
                      ->orWhere('customers.phone','like',"%$q%")
                      ->orWhere('customers.document','like',"%$q%")
                      ->orWhere('customers.email','like',"%$q%");
                });
            })
            ->groupBy('customers.id','customers.name','customers.phone','customers.document','customers.email')
            ->select(
                'customers.id','customers.name','customers.phone','customers.document','customers.email',
                DB::raw('COUNT(orders.id) as total_orders'),
                DB::raw('COALESCE(SUM(orders.total),0) as total_spent'),
                DB::raw('MAX(orders.created_at) as last_order_at')
            )
            ->orderByDesc('total_spent')
            ->paginate(25)
            ->withQueryString();

        return view('reports.customers', compact('rows','q','from','to'));
    }

    public function customersCsv(Request $r)
    {
        $q    = trim((string)$r->get('q',''));
        $from = $r->date('from');
        $to   = $r->date('to');

        $data = Customer::query()
            ->leftJoin('orders','orders.customer_id','=','customers.id')
            ->when($from && $to, fn($w)=>$w->whereBetween('orders.created_at', [$from->startOfDay(), $to->endOfDay()]))
            ->when($q, function($w) use ($q) {
                $w->where(function($x) use ($q) {
                    $x->where('customers.name','like',"%$q%")
                      ->orWhere('customers.phone','like',"%$q%")
                      ->orWhere('customers.document','like',"%$q%")
                      ->orWhere('customers.email','like',"%$q%");
                });
            })
            ->groupBy('customers.id','customers.name','customers.phone','customers.document','customers.email')
            ->select(
                'customers.id','customers.name','customers.phone','customers.document','customers.email',
                DB::raw('COUNT(orders.id) as total_orders'),
                DB::raw('COALESCE(SUM(orders.total),0) as total_spent'),
                DB::raw('MAX(orders.created_at) as last_order_at')
            )
            ->orderByDesc('total_spent')
            ->get();

        $filename = 'relatorio_clientes.csv';
        return response()->streamDownload(function() use ($data) {
            $out = fopen('php://output','w');
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($out, ['ID','Nome','Telefone','Documento','Email','Qtde Pedidos','Total (R$)','Última compra'], ';');
            foreach ($data as $r) {
                fputcsv($out, [
                    $r->id,
                    $r->name,
                    $r->phone,
                    $r->document,
                    $r->email,
                    $r->total_orders,
                    number_format($r->total_spent,2,',','.'),
                    $r->last_order_at ? \Carbon\Carbon::parse($r->last_order_at)->format('d/m/Y H:i') : ''
                ], ';');
            }
            fclose($out);
        }, $filename, ['Content-Type'=>'text/csv; charset=UTF-8']);
    }
}