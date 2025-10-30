<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\{
    ProductController, PosController, OrderController, CategoryController,
    EmployeeController, ReportsController, CashRegisterController,
    CustomerController, ReportController
};
use App\Http\Controllers\ServiceOrderController;

// Auth (Breeze/Jetstream)
require __DIR__.'/auth.php';
Route::get('/dashboard', fn () => view('dashboard'))
    ->middleware(['auth','verified'])
    ->name('dashboard');

// Tudo abaixo exige login
Route::middleware(['auth'])->group(function () {

    // Home -> PDV
    Route::get('/', fn () => redirect()->route('pos.index'))->name('home');

    /* ===================== PDV (todos os logados) ===================== */
    // --- Caixa: permitir seller, manager e admin ---
    Route::middleware('role:seller,manager,admin')->group(function () {
    Route::get('/cash',               [CashRegisterController::class,'index'])->name('cash.index');
    Route::get('/cash/close-preview', [CashRegisterController::class,'closePreview'])->name('cash.close.preview');
    Route::get('/cash/close-export',  [CashRegisterController::class,'exportCsv'])->name('cash.close.export');
    Route::post('/cash/open',         [CashRegisterController::class,'open'])->name('cash.open');
    Route::post('/cash/close',        [CashRegisterController::class,'close'])->name('cash.close');
    Route::post('/cash/movement',     [CashRegisterController::class,'movement'])->name('cash.movement');
    Route::get('/cash/close-print', [CashRegisterController::class,'closePrint'])->name('cash.close.print');
    });
    Route::get('/pos', [PosController::class,'index'])->name('pos.index');
    Route::get('/pos/find', [PosController::class,'find'])->name('pos.find');
    Route::post('/pos/checkout', [PosController::class,'checkout'])->name('pos.checkout');

    // Clientes rápidos para o PDV
    Route::get('/customers/find',  [CustomerController::class,'find'])->name('customers.find');
    Route::post('/customers/quick',[CustomerController::class,'quick'])->name('customers.quick');

    /* ====== Rotas de apoio ao PDV liberadas para qualquer logado ====== */
    Route::get('/products-search', [ProductController::class,'search'])->name('products.search');

    /* ===================== Pedidos (todos os logados) ===================== */
    Route::get('/orders',                         [OrderController::class,'index'])->name('orders.index');
    Route::get('/orders/{order}',                 [OrderController::class,'show'])->name('orders.show');
    Route::get('/orders/{order}/receipt',         [OrderController::class,'receipt'])->name('orders.receipt');
    Route::get('/orders/{order}/receipt-simple',  [OrderController::class,'receiptSimple'])->name('orders.receipt.simple');
    Route::post('/orders/{order}/finalize',       [OrderController::class,'finalize'])->name('orders.finalize');
    Route::post('/orders/{order}/cancel',         [OrderController::class,'cancel'])->name('orders.cancel');

    /* ===================== Restrito a manager/admin ===================== */
    Route::middleware('role:manager,admin')->group(function () {

        // Produtos
        Route::post('/products/labels-batch', [ProductController::class, 'labelsBatch'])
            ->name('products.labels.batch');

        Route::get('/products/{product}/history', [ProductController::class, 'history'])
            ->name('products.history');

        Route::get('/products/import',  [ProductController::class,'importForm'])
            ->name('products.import.form');
        Route::post('/products/import', [ProductController::class,'import'])
            ->name('products.import');
        Route::get('/products/export.csv', [ProductController::class,'exportCsv'])
            ->name('products.export.csv');

        Route::resource('products', ProductController::class)->except(['show','destroy']);

        Route::post('/products/{product}/toggle', [ProductController::class,'toggle'])
            ->name('products.toggle');

        Route::get('/products/{product}/label', [ProductController::class,'label'])
            ->name('products.label');

        Route::post('/products/{product}/adjust', [ProductController::class,'adjustStock'])
            ->name('products.adjust');

        // Categorias
        Route::resource('categories', CategoryController::class)->except(['create','edit','show']);
        Route::post('/categories/{category}/toggle', [CategoryController::class,'toggle'])
            ->name('categories.toggle');

        // Relatórios extras
        Route::get('/reports/sales/summary',      [ReportsController::class,'salesSummary'])->name('reports.sales.summary');
        Route::get('/reports/sales/summary.csv',  [ReportsController::class,'salesSummaryCsv'])->name('reports.sales.summary.csv');

        Route::get('/reports/sales/products',     [ReportsController::class,'salesByProduct'])->name('reports.sales.products');
        Route::get('/reports/sales/products.csv', [ReportsController::class,'salesByProductCsv'])->name('reports.sales.products.csv');

        Route::get('/reports/sales/categories',     [ReportsController::class,'salesByCategory'])->name('reports.sales.categories');
        Route::get('/reports/sales/categories.csv', [ReportsController::class,'salesByCategoryCsv'])->name('reports.sales.categories.csv');

        Route::get('/reports/stock/history',      [ReportsController::class,'stockHistory'])->name('reports.stock.history');
        Route::get('/reports/stock/history.csv',  [ReportsController::class,'stockHistoryCsv'])->name('reports.stock.history.csv');

        // Relatório de clientes
        Route::get('/reports/customers',     [ReportController::class,'customers'])->name('reports.customers');
        Route::get('/reports/customers.csv', [ReportController::class,'customersCsv'])->name('reports.customers.csv');
    });

    Route::middleware(['auth','role:seller,manager,admin'])->group(function () {
    Route::get('/os', [ServiceOrderController::class,'index'])->name('os.index');
    Route::get('/os/create', [ServiceOrderController::class,'create'])->name('os.create');
    Route::post('/os', [ServiceOrderController::class,'store'])->name('os.store');
    Route::get('/os/{os}/edit', [ServiceOrderController::class,'edit'])->name('os.edit');
    Route::put('/os/{os}', [ServiceOrderController::class,'update'])->name('os.update');

    Route::post('/os/{os}/items', [ServiceOrderController::class,'addItem'])->name('os.items.add');
    Route::delete('/os/{os}/items/{item}', [ServiceOrderController::class,'removeItem'])->name('os.items.remove');

    Route::post('/os/{os}/to-pos', [ServiceOrderController::class,'toPos'])->name('os.to.pos');
});
    /* ===================== Somente admin ===================== */
    Route::middleware('role:admin')->group(function () {
        Route::resource('employees', EmployeeController::class)
            ->parameters(['employees' => 'employee'])
            ->except(['create','edit','show']);
    });
});
