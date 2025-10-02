<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndAdminSeeder extends Seeder
{
    public function run(): void
    {
        // Permissões principais
        $perms = [
            'products.view','products.create','products.update','products.deactivate',
            'pos.sell','orders.cancel','orders.view',
            'stock.adjust','reports.view',
            'employees.manage','categories.manage',
        ];

        foreach ($perms as $p) {
            Permission::firstOrCreate(['name' => $p]);
        }

        // Papéis
        $admin   = Role::firstOrCreate(['name' => 'admin']);
        $gerente = Role::firstOrCreate(['name' => 'gerente']);
        $vendedor= Role::firstOrCreate(['name' => 'vendedor']);
        $estoque = Role::firstOrCreate(['name' => 'estoque']);

        // Liga permissões por papel
        $admin->syncPermissions(Permission::all());
        $gerente->syncPermissions([
            'products.view','products.create','products.update','products.deactivate',
            'pos.sell','orders.cancel','orders.view',
            'stock.adjust','reports.view','categories.manage'
        ]);
        $vendedor->syncPermissions(['products.view','pos.sell','orders.view']);
        $estoque->syncPermissions(['products.view','stock.adjust','categories.manage']);

        // Usuário admin padrão
        if (!User::where('email','admin@motoshop.local')->exists()) {
            $user = User::create([
                'name' => 'Admin Master',
                'email'=> 'admin@motoshop.local',
                'password' => Hash::make('admin123'), // troque depois!
            ]);
            $user->assignRole('admin');
        }
    }
}
