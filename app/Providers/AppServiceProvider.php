<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\View;
use App\Models\CashRegister;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Mantém isso
        Paginator::useBootstrapFive();

        // Adiciona isso
        View::composer('*', function ($view) {
            $cashOpen = CashRegister::where('status', 'open')->exists();
            $view->with('cashOpen', $cashOpen);
        });
    }
}