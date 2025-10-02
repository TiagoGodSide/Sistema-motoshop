<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use App\Models\Product;
use App\Models\Category;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $catOleo = Category::where('name','Óleos e Lubrificantes')->first();
        $catPast = Category::where('name','Pastilhas e Sapatas')->first();
        $catPneu = Category::where('name','Pneus')->first();
        $catElec = Category::where('name','Elétrica')->first();

        $products = [
            [
                'name'  => 'Óleo 10W40 Semissintético',
                'sku'   => 'OLEO10W40',
                'ean'   => null,
                'internal_barcode' => 'INT-'.strtoupper(Str::random(8)),
                'price' => 49.90, 'cost_price' => 33.00, 'stock' => 24,
                'category_id' => optional($catOleo)->id
            ],
            [
                'name'  => 'Pastilha de Freio 125cc',
                'sku'   => 'PAST-FREIO-125',
                'ean'   => '7891234567890',
                'internal_barcode' => 'INT-'.strtoupper(Str::random(8)),
                'price' => 35.00, 'cost_price' => 18.00, 'stock' => 12,
                'category_id' => optional($catPast)->id
            ],
            [
                'name'  => 'Pneu 90/90-18 Dianteiro',
                'sku'   => 'PNEU-90-90-18',
                'ean'   => null,
                'internal_barcode' => 'INT-'.strtoupper(Str::random(8)),
                'price' => 299.00, 'cost_price' => 210.00, 'stock' => 6,
                'category_id' => optional($catPneu)->id
            ],
            [
                'name'  => 'Vela de Ignição NGK',
                'sku'   => 'VELA-NGK',
                'ean'   => '7890001112223',
                'internal_barcode' => 'INT-'.strtoupper(Str::random(8)),
                'price' => 29.50, 'cost_price' => 15.00, 'stock' => 18,
                'category_id' => optional($catElec)->id
            ],
        ];

        foreach ($products as $p) {
            Product::firstOrCreate(['sku' => $p['sku']], $p);
        }
    }
}
