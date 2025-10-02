<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Milon\Barcode\DNS1D;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $q   = trim($request->get('q', ''));
        $tab = $request->get('tab', 'ativos'); // ativos|desativados|sem-estoque

        $products = Product::query()
            ->when($q, function ($qry) use ($q) {
                $qry->where(function ($w) use ($q) {
                    $w->where('name', 'like', "%{$q}%")
                      ->orWhere('sku', 'like', "%{$q}%")
                      ->orWhere('ean', 'like', "%{$q}%")
                      ->orWhere('internal_barcode', 'like', "%{$q}%");
                });
            })
            ->when($tab === 'desativados', fn($qry) => $qry->where('is_active', false))
            ->when($tab === 'sem-estoque', fn($qry) => $qry->where('stock', '<=', 0)->where('is_active', true))
            ->when($tab === 'ativos', fn($qry) => $qry->where('is_active', true))
            ->latest('id')
            ->paginate(20)
            ->appends($request->query());

        return view('products.index', compact('products','q','tab'));
       // return response()->json($products); // provisório
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'   => ['required','string','max:255'],
            'sku'    => ['required','string','max:100','unique:products,sku'],
            'ean'    => ['nullable','string','max:50'],
            'price'  => ['required','numeric','min:0'],
            'cost_price' => ['nullable','numeric','min:0'],
            'stock'  => ['required','integer','min:0'],
            'min_stock' => ['nullable','integer','min:0'],
            'category_id' => ['nullable','exists:categories,id'],
            'unit' => ['nullable','string','max:10'],
            'generate_internal' => ['nullable','boolean'], // checkbox opcional
        ]);

        if (blank($request->internal_barcode)) {
            $data['internal_barcode'] = $this->makeInternalCode();
        }

        $product = Product::create($data);

        // movimento de estoque inicial (se > 0)
        if ($product->stock > 0) {
            StockMovement::create([
                'product_id' => $product->id,
                'type'       => 'IN',
                'qty'        => $product->stock,
                'unit_price' => $product->cost_price,
                'reason'     => 'Estoque inicial',
                'user_id'    => optional($request->user())->id,
            ]);
        }

        return redirect()->route('products.index')->with('ok','Produto cadastrado');
        //return response()->json($product, 201);
    }

    public function show(Product $product)
    {
        return view('products.show', compact('product'));
        //return response()->json($product);
    }

    public function update(Request $request, Product $product)
    {
        $data = $request->validate([
            'name'   => ['required','string','max:255'],
            'sku'    => ['required','string','max:100',"unique:products,sku,{$product->id}"],
            'ean'    => ['nullable','string','max:50'],
            'price'  => ['required','numeric','min:0'],
            'cost_price' => ['nullable','numeric','min:0'],
            'stock'  => ['required','integer','min:0'],
            'min_stock' => ['nullable','integer','min:0'],
            'category_id' => ['nullable','exists:categories,id'],
            'unit' => ['nullable','string','max:10'],
            'is_active' => ['nullable','boolean'],
        ]);

        if (blank($product->internal_barcode)) {
            $data['internal_barcode'] = $this->makeInternalCode();
        }

        $product->update($data);

        // TODO: redirect back
        return response()->json($product);
    }

    public function destroy(Product $product)
    {
        // Em vez de excluir, desativa
        $product->update(['is_active' => false]);
        return response()->json(['ok' => true]);
    }

    public function activate(Product $product)
    {
        $product->update(['is_active' => true]);
        return response()->json(['ok' => true]);
    }

    public function label(Product $product)
    {
        $dns1d = new DNS1D;
        $svg   = $dns1d->getBarcodeSVG($product->internal_barcode, 'C128', 2, 60);
        return view('products.label', ['product'=>$product,'barcodeSvg'=>$svg]);
        //return response()->view('products.label', ['product'=>$product,'barcodeSvg'=>$svg]);
    }

    public function search(Request $request)
    {
        $q = trim($request->get('q', ''));
        abort_if($q === '', 422, 'Informe q');

        $products = Product::query()
            ->where(function($w) use ($q) {
                $w->where('name','like',"%{$q}%")
                  ->orWhere('sku','like',"%{$q}%")
                  ->orWhere('ean','like',"%{$q}%")
                  ->orWhere('internal_barcode','like',"%{$q}%");
            })
            ->where('is_active', true)
            ->orderBy('name')
            ->limit(20)
            ->get(['id','name','sku','ean','internal_barcode','price','stock']);

        return response()->json($products);
    }

    protected function makeInternalCode(): string
    {
        // Padrão simples: INT-XXXXXXXX (ajuste para seu padrão preferido)
        return 'INT-' . strtoupper(Str::random(8));
    }
}




