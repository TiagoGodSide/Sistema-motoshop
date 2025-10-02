<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $cats = [
            'Óleos e Lubrificantes',
            'Pastilhas e Sapatas',
            'Pneus',
            'Elétrica',
            'Acessórios',
        ];
        foreach ($cats as $c) {
            Category::firstOrCreate(['name' => $c], ['is_active' => true]);
        }
    }
}
