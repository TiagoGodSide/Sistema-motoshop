<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\StockMovement;
use App\Models\ProductPriceHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Milon\Barcode\DNS1D;
use App\Models\Category;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;


class ProductController extends Controller
{
    public function index(Request $request)
{
            $q   = trim($request->get('q', ''));
            $tab = $request->get('tab', 'ativos'); // ativos|desativados|sem-estoque|baixo-minimo

            $qry = Product::query()
                ->when($q, function ($qry) use ($q) {
                    $qry->where(function ($w) use ($q) {
                        $w->where('name', 'like', "%{$q}%")
                        ->orWhere('sku', 'like', "%{$q}%")
                        ->orWhere('ean', 'like', "%{$q}%")
                        ->orWhere('internal_barcode', 'like', "%{$q}%");
                    });
                });

            $products = (clone $qry)
                ->when($tab === 'desativados', fn($w)=>$w->where('is_active', false))
                ->when($tab === 'sem-estoque', fn($w)=>$w->where('stock','<=',0)->where('is_active', true))
                ->when($tab === 'baixo-minimo', fn($w)=>$w->belowMin())
                ->when($tab === 'ativos', fn($w)=>$w->where('is_active', true))
                ->latest('id')
                ->paginate(20)
                ->appends($request->query());

            // Contadores para badges
            $noStockCount = (clone $qry)->where('is_active', true)->where('stock','<=',0)->count();
            $lowCount     = (clone $qry)->belowMin()->count();

            return view('products.index', compact('products','q','tab','noStockCount','lowCount'));
        }

            public function lowStockCsv(): StreamedResponse
            {
            $rows = \App\Models\Product::belowMin()->orderBy('name')->get([
                'name','sku','ean','internal_barcode','stock','min_stock','price','unit'
            ]);

            return response()->streamDownload(function() use ($rows){
                $out = fopen('php://output','w');
                fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM p/ Excel
                fputcsv($out, ['name','sku','ean','internal_barcode','stock','min_stock','price','unit'], ';');
                foreach ($rows as $p) {
                    fputcsv($out, [
                        $p->name, $p->sku, $p->ean, $p->internal_barcode,
                        (int)$p->stock, (int)$p->min_stock,
                        number_format((float)$p->price,2,',',''),
                        $p->unit
                    ], ';');
                }
                fclose($out);
            }, 'produtos_abaixo_minimo.csv', ['Content-Type'=>'text/csv; charset=UTF-8']);
        }


   /** Salvar novo */
    public function store(Request $request)
    {
         $data = $request->validate([
                'name'             => 'required|string|max:180',
                'sku'              => 'nullable|string|max:60|unique:products,sku',
                'ean'              => 'nullable|string|max:60|unique:products,ean',
                'internal_barcode' => 'nullable|string|max:60|unique:products,internal_barcode',
                'price'            => 'required|numeric|min:0',
                'cost_price'       => 'nullable|numeric|min:0',
                'stock'            => 'nullable|integer|min:0',
                'min_stock'        => 'nullable|integer|min:0',
                'unit'             => 'nullable|string|max:10',
                'category_id'      => 'nullable|exists:categories,id',
                'is_active'        => 'nullable|boolean',
            ]);
    $data['unit'] = $data['unit'] ?? 'UN'; // padrão opcional

        if (empty($data['internal_barcode'])) {
            $data['internal_barcode'] = $this->generateInternalBarcode();
        }
        $data['is_active'] = $request->boolean('is_active', true);

        $product = Product::create($data);

        if ($request->wantsJson()) {
            return response()->json(['ok'=>true, 'id'=>$product->id], 201);
        }
        return redirect()->route('products.index')->with('ok','Produto criado.');
    }


    public function show(Product $product)
    {
        return view('products.show', compact('product'));
        //return response()->json($product);
    }

    public function edit(Product $product)
{
    $categories = Category::orderBy('name')->get();
    return view('products.edit', compact('product','categories'));
}

    
    public function update(Request $request, Product $product)
    {
        $data = $request->validate([
            'name'             => 'required|string|max:180',
            'sku'              => 'nullable|string|max:60|unique:products,sku,'.$product->id,
            'ean'              => 'nullable|string|max:60|unique:products,ean,'.$product->id,
            'internal_barcode' => 'nullable|string|max:60|unique:products,internal_barcode,'.$product->id,
            'price'            => 'required|numeric|min:0',
            'cost_price'       => 'nullable|numeric|min:0',
            'stock'            => 'nullable|integer|min:0',
            'min_stock'        => 'nullable|integer|min:0',
            'unit'             => 'nullable|string|max:10',
            'category_id'      => 'nullable|exists:categories,id',
            'is_active'        => 'nullable|boolean',
        ]);

        $data['is_active'] = $request->boolean('is_active');
        
        $oldPrice = $product->price;
        $priceChanged = array_key_exists('price',$data) && (float)$data['price'] !== (float)$oldPrice;

        $product->update($data);

        if ($priceChanged) {
    \App\Models\ProductPriceHistory::create([
        'product_id' => $product->id,
        'old_price'  => $oldPrice,
        'new_price'  => (float)$product->price,
        'user_id'    => optional($request->user())->id,
    ]);
    }

        if ($request->wantsJson()) {
            return response()->json(['ok'=>true, 'id'=>$product->id]);
        }
        return redirect()->route('products.index')->with('ok','Produto atualizado.');
        
    }

        public function destroy(Product $product)
{
        $product->delete();
        return back()->with('ok','Produto excluído.');
}

        public function label(Product $product)
        {
            $code = $product->internal_barcode ?: ($product->ean ?: $product->sku ?: (string)$product->id);
            $dns1d = new DNS1D();
            $svg = $dns1d->getBarcodeSVG($code, 'C128', 2, 60);
            return view('products.label', ['product'=>$product, 'barcodeSvg'=>$svg, 'code'=>$code]);
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

    public function exportCsv(): StreamedResponse
{
            $rows = Product::orderBy('name')->get([
                'name','sku','ean','internal_barcode','price','cost_price','stock','min_stock','unit','category_id','is_active'
            ])->map(function($p){
                return [
                    'name'   => $p->name,
                    'sku'    => $p->sku,
                    'ean'    => $p->ean,
                    'internal_barcode' => $p->internal_barcode,
                    'price'  => number_format((float)$p->price, 2, ',', ''),
                    'cost_price' => number_format((float)$p->cost_price, 2, ',', ''),
                    'stock'  => (int)$p->stock,
                    'min_stock' => (int)$p->min_stock,
                    'unit'   => $p->unit,
                    'category' => optional($p->category)->name,
                    'is_active' => $p->is_active ? 1 : 0,
                ];
            });

            return response()->streamDownload(function() use ($rows) {
                $out = fopen('php://output','w');
                fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM (Excel)
                $head = ['name','sku','ean','internal_barcode','price','cost_price','stock','min_stock','unit','category','is_active'];
                fputcsv($out, $head, ';');
                foreach ($rows as $r) fputcsv($out, $r, ';');
                fclose($out);
            }, 'products_export.csv', ['Content-Type'=>'text/csv; charset=UTF-8']);
        }

        public function templateCsv(): StreamedResponse
        {
            return response()->streamDownload(function(){
                $out = fopen('php://output','w');
                fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
                $head = ['name','sku','ean','internal_barcode','price','cost_price','stock','min_stock','unit','category','is_active'];
                fputcsv($out, $head, ';');
                fputcsv($out, ['Óleo 10W40','OLEO10W40','','','49,90','39,00','24','2','UN','Óleos e Lubrificantes','1'], ';');
                fclose($out);
            }, 'products_template.csv', ['Content-Type'=>'text/csv; charset=UTF-8']);
        }

        public function importForm()
        {
            return view('products.import');
        }

        public function importProcess(Request $request)
        {
            $request->validate([
                'file'   => 'required|file|mimes:csv,txt',
                'mode'   => 'required|in:upsert,insert',
            ]);

            $file = $request->file('file');

            // Lê CSV (aceita ; ou ,)
            $rows = [];
            if (($h = fopen($file->getRealPath(), 'r')) !== false) {
                // BOM
                $first = fgets($h);
                if ($first === false) return back()->withErrors('Arquivo vazio.');
                $first = ltrim($first, "\xEF\xBB\xBF"); // remove BOM
                $delim = (substr_count($first, ';') >= substr_count($first, ',')) ? ';' : ',';
                $header = str_getcsv(trim($first), $delim);
                $map = array_map(fn($c)=>strtolower(trim($c)), $header);

                while (($line = fgets($h)) !== false) {
                    if (trim($line) === '') continue;
                    $cols = str_getcsv(rtrim($line), $delim);
                    $row  = [];
                    foreach ($cols as $i=>$v) {
                        $key = $map[$i] ?? ('col'.$i);
                        $row[$key] = trim($v);
                    }
                    $rows[] = $row;
                }
                fclose($h);
            }

            $toFloat = function($s){
                $s = str_replace(['.', ' '], '', $s);
                $s = str_replace(',', '.', $s);
                return is_numeric($s) ? (float)$s : null;
            };

            $created = 0; $updated = 0; $errors = [];
            foreach ($rows as $idx => $r) {
                // Validação leve por linha
                $v = Validator::make($r, [
                    'name'   => 'required|string|max:180',
                    'sku'    => 'nullable|string|max:60',
                    'ean'    => 'nullable|string|max:60',
                    'internal_barcode' => 'nullable|string|max:60',
                    'price'  => 'nullable',
                    'cost_price' => 'nullable',
                    'stock'  => 'nullable|integer',
                    'min_stock' => 'nullable|integer',
                    'unit'   => 'nullable|string|max:10',
                    'category' => 'nullable|string|max:120',
                    'is_active' => 'nullable|in:0,1',
                ]);
                if ($v->fails()) {
                    $errors[] = "Linha ".($idx+2).": ".implode('; ', $v->errors()->all());
                    continue;
                }

                // categoria: cria se não existir
                $categoryId = null;
                if (!empty($r['category'])) {
                    $cat = \App\Models\Category::firstOrCreate(['name'=>$r['category']]);
                    $categoryId = $cat->id;
                }

                $payload = [
                    'name'   => $r['name'] ?? null,
                    'price'  => isset($r['price']) ? $toFloat($r['price']) : null,
                    'cost_price' => isset($r['cost_price']) ? $toFloat($r['cost_price']) : null,
                    'stock'  => isset($r['stock']) ? (int)$r['stock'] : null,
                    'min_stock' => isset($r['min_stock']) ? (int)$r['min_stock'] : null,
                    'unit'   => $r['unit'] ?? 'UN',
                    'category_id' => $categoryId,
                    'is_active' => isset($r['is_active']) ? ((int)$r['is_active'] === 1) : true,
                ];

        // Chave de busca p/ upsert: sku > ean > internal_barcode
        $key = null; $keyField = null;
        foreach (['sku','ean','internal_barcode'] as $f) {
            if (!empty($r[$f])) { $key = $r[$f]; $keyField = $f; break; }
        }

        try {
            if ($request->mode === 'insert' || $key === null) {
                // Apenas inserir (gera código interno se vier vazio – já temos no model)
                $payload['sku'] = $r['sku'] ?? null;
                $payload['ean'] = $r['ean'] ?? null;
                $payload['internal_barcode'] = $r['internal_barcode'] ?? null;
                Product::create($payload);
                $created++;
            } else {
                // UPSERT por chave encontrada
                $where = [$keyField => $key];
                $exists = Product::where($where)->first();
                if ($exists) {
                    $exists->update(array_filter($payload, fn($v)=>!is_null($v)));
                    $updated++;
                } else {
                    $payload[$keyField] = $key;
                    Product::create($payload);
                    $created++;
                }
            }
        } catch (\Throwable $e) {
            $errors[] = "Linha ".($idx+2).": ".$e->getMessage();
        }
    }

    $msg = "Importação concluída: {$created} criados, {$updated} atualizados.";
    if (!empty($errors)) {
        return back()->with('ok', $msg)->withErrors($errors);
    }
    return redirect()->route('products.index')->with('ok', $msg);
}


    protected function makeInternalCode(): string
    {
        // Padrão simples: INT-XXXXXXXX (ajuste para seu padrão preferido)
        return 'INT-' . strtoupper(Str::random(8));
    }

    public function toggle(Request $request, Product $product)
{
    $product->is_active = ! $product->is_active;
    $product->save();

    if ($request->wantsJson()) {
        return response()->json(['ok'=>true,'is_active'=>$product->is_active]);
    }
    return back()->with('ok', 'Produto '.($product->is_active?'ativado':'inativado').'.');
}

     /** Tela de criação */
    public function create()
    {
        $categories = Category::orderBy('name')->get();
        $product = new Product();          // para reusar o form
        $product->is_active = true;        // padrão
        return view('products.create', compact('product','categories'));
    }

    /** Gera código interno único (se não informado) */
    protected function generateInternalBarcode(): string
    {
        do {
            $code = 'I'.str_pad((string)random_int(1, 99999999), 8, '0', STR_PAD_LEFT);
        } while (Product::where('internal_barcode', $code)->exists());
        return $code;
    }

    public function history(Product $product)
{
    // últimos 100 movimentos
    $moves = StockMovement::where('product_id',$product->id)
        ->latest('id')->limit(100)->get();

    // últimas 50 mudanças de preço
    $prices = ProductPriceHistory::where('product_id',$product->id)
        ->latest('id')->limit(50)->get();

    return view('products.history', compact('product','moves','prices'));
}

    public function historyCsv(Product $product): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $moves = StockMovement::where('product_id',$product->id)->orderBy('id')->get();
        $prices = ProductPriceHistory::where('product_id',$product->id)->orderBy('id')->get();

        return response()->streamDownload(function() use ($product,$moves,$prices){
            $out = fopen('php://output','w');
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

            // Bloco 1: Movimentos de estoque
            fputcsv($out, ['HISTÓRICO DE ESTOQUE — '.$product->name], ';');
            fputcsv($out, ['data','tipo','quantidade','unit_price','reason','user_id'], ';');
            foreach ($moves as $m) {
                fputcsv($out, [
                    optional($m->created_at)->format('d/m/Y H:i'),
                    $m->type, $m->qty,
                    number_format((float)$m->unit_price,2,',',''),
                    $m->reason,
                    $m->user_id,
                ], ';');
            }
            fputcsv($out, [''], ';'); // linha em branco

            // Bloco 2: Mudanças de preço
            fputcsv($out, ['HISTÓRICO DE PREÇO — '.$product->name], ';');
            fputcsv($out, ['data','old_price','new_price','user_id'], ';');
            foreach ($prices as $p) {
                fputcsv($out, [
                    optional($p->created_at)->format('d/m/Y H:i'),
                    is_null($p->old_price) ? '' : number_format((float)$p->old_price,2,',',''),
                    number_format((float)$p->new_price,2,',',''),
                    $p->user_id,
                ], ';');
            }
            fclose($out);
        }, 'produto_'.$product->id.'_historico.csv', ['Content-Type'=>'text/csv; charset=UTF-8']);
    }

    public function adjustStock(Request $request, \App\Models\Product $product)
        {
            $data = $request->validate([
                'type'        => 'required|in:in,out',          // entrada ou saída
                'qty'         => 'required|integer|min:1',
                'unit_price'  => 'nullable|numeric|min:0',
                'reason_code' => 'nullable|in:inventory,loss,exchange,gift,other',
                'reason'      => 'nullable|string|max:180',     // texto livre (complemento)
                'attachment'  => 'nullable|file|max:4096|mimes:jpg,jpeg,png,pdf',
            ]);

            DB::transaction(function () use ($product, $data, $request) {
                $p = \App\Models\Product::whereKey($product->id)->lockForUpdate()->first();

                $delta    = $data['type'] === 'in' ? $data['qty'] : -$data['qty'];
                $newStock = (int)$p->stock + $delta;
                if ($newStock < 0) {
                    abort(\Symfony\Component\HttpFoundation\Response::HTTP_UNPROCESSABLE_ENTITY, 'Estoque insuficiente para saída.');
                }

                $p->stock = $newStock;
                $p->save();

                $path = null;
                if (!empty($data['attachment'])) {
                    $path = $request->file('attachment')->store('stock_movements', 'public');
                }

                \App\Models\StockMovement::create([
                    'product_id'     => $p->id,
                    'type'           => $data['type'],
                    'qty'            => $data['qty'],
                    'unit_price'     => $data['unit_price'] ?? $p->cost_price,
                    'reason'         => $data['reason'] ?? null,
                    'reason_code'    => $data['reason_code'] ?? null,
                    'attachment_path'=> $path,
                    'user_id'        => optional($request->user())->id,
                ]);
            });

            return back()->with('ok', 'Estoque ajustado com sucesso.');
        }

       public function labelsBatch(Request $request)
{
    // aceita labels[ID]=QTD (ou items[ID]=QTD como fallback)
    $map = $request->input('labels', []);
    if (empty($map)) {
        $map = $request->input('items', []);
    }

    // normaliza: apenas quantidades inteiras > 0
    $labels = [];
    foreach ($map as $id => $qty) {
        $q = (int) $qty;
        if ($q > 0) {
            $labels[(int)$id] = $q;
        }
    }

    if (empty($labels)) {
        return back()->withErrors('Selecione pelo menos 1 produto e informe a quantidade de etiquetas.');
    }

    // busca produtos
    $products = Product::whereIn('id', array_keys($labels))
        ->get(['id','name','sku','ean','internal_barcode','price'])
        ->keyBy('id');

    // gera SVGs dos códigos
    $dns1d = new DNS1D();
    $dns1d->setStorPath(storage_path('framework/cache'));

    $itemsToPrint = []; // cada item = uma etiqueta a ser impressa
    foreach ($labels as $id => $qty) {
        $p = $products->get($id);
        if (!$p) continue;

        $code = $p->internal_barcode ?: ($p->ean ?: ($p->sku ?: (string)$p->id));
        $svg  = $dns1d->getBarcodeSVG($code, 'C128', 2, 60);

        for ($i = 0; $i < $qty; $i++) {
            $itemsToPrint[] = [
                'name'  => $p->name,
                'code'  => $code,
                'price' => $p->price,
                'svg'   => $svg,
            ];
        }
    }

    // único return do método
    return view('products.labels-batch', [
        'items' => $itemsToPrint,
    ]);

}

}



