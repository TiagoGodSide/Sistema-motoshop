<?php

use App\Http\Controllers\CashRegisterController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\ServiceOrderController;
use Illuminate\Support\Facades\Route;

// Auth (Breeze/Jetstream)
require __DIR__.'/auth.php';
Route::get('/dashboard', fn () => view('dashboard'))
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

// Tudo abaixo exige login
Route::middleware(['auth', 'role:admin|gerente|vendedor|estoque'])->group(function () {

    Route::get('/', fn () => redirect()->route(
        request()->user()->can('pos.sell') ? 'pos.index' : 'products.index'
    ))->name('home');

    // Pendente: separar permissões de consultar, movimentar e fechar caixa.
    Route::middleware('role:admin|gerente|vendedor')->group(function () {
        Route::get('/cash', [CashRegisterController::class, 'index'])->name('cash.index');
        Route::get('/cash/close-preview', [CashRegisterController::class, 'closePreview'])->name('cash.close.preview');
        Route::get('/cash/close-export', [CashRegisterController::class, 'exportCsv'])->name('cash.close.export');
        Route::post('/cash/open', [CashRegisterController::class, 'open'])->name('cash.open');
        Route::post('/cash/close', [CashRegisterController::class, 'close'])->name('cash.close');
        Route::post('/cash/movement', [CashRegisterController::class, 'movement'])->name('cash.movement');
        Route::get('/cash/close-print', [CashRegisterController::class, 'closePrint'])->name('cash.close.print');
    });
    Route::get('/pos', [PosController::class, 'index'])->middleware(['can:pos.sell'])->name('pos.index');
    Route::get('/pos/find', [PosController::class, 'find'])->middleware(['can:pos.sell'])->name('pos.find');
    Route::post('/pos/checkout', [PosController::class, 'checkout'])->middleware(['can:pos.sell'])->name('pos.checkout');

    // Clientes rápidos para o PDV
    Route::get('/customers/find', [CustomerController::class, 'find'])->middleware(['can:pos.sell'])->name('customers.find');
    Route::post('/customers/quick', [CustomerController::class, 'quick'])->middleware(['can:pos.sell'])->name('customers.quick');

    // Busca de produtos conforme a permissão de consulta.
    Route::get('/products-search', [ProductController::class, 'search'])->middleware(['can:products.view'])->name('products.search');

    // Pedidos com permissões por operação.
    Route::get('/orders', [OrderController::class, 'index'])->middleware(['can:orders.view'])->name('orders.index');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->middleware(['can:orders.view'])->name('orders.show');
    Route::get('/orders/{order}/receipt', [OrderController::class, 'receipt'])->middleware(['can:orders.view'])->name('orders.receipt');
    Route::get('/orders/{order}/receipt-simple', [OrderController::class, 'receiptSimple'])->middleware(['can:orders.view'])->name('orders.receipt.simple');
    Route::post('/orders/{order}/finalize', [OrderController::class, 'finalize'])->middleware(['can:orders.view', 'can:pos.sell'])->name('orders.finalize');
    Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel'])->middleware(['can:orders.cancel'])->name('orders.cancel');

    // Catálogo, categorias e relatórios com permissões por operação.

    // Produtos
    Route::post('/products/labels-batch', [ProductController::class, 'labelsBatch'])
        ->middleware(['can:products.update'])->name('products.labels.batch');

    Route::get('/products/{product}/history', [ProductController::class, 'history'])
        ->middleware(['can:products.update'])->name('products.history');

    Route::get('/products/import', [ProductController::class, 'importForm'])
        ->middleware(['can:products.create', 'can:products.update'])->name('products.import.form');
    // Pendente: revisar valores monetários, transações e históricos de preço/estoque da importação.
    Route::post('/products/import', [ProductController::class, 'importProcess'])
        ->middleware(['can:products.create', 'can:products.update'])->name('products.import');
    Route::get('/products/export.csv', [ProductController::class, 'exportCsv'])
        ->middleware(['can:products.update'])->name('products.export.csv');
    Route::get('/products/low-stock.csv',
        [ProductController::class, 'exportLowStockCsv']
    )->middleware(['can:products.update'])->name('products.low.csv');
    Route::get('/products/template.csv', [ProductController::class, 'templateCsv'])
        ->middleware(['can:products.create', 'can:products.update'])->name('products.template.csv');

    Route::resource('products', ProductController::class)->only('index')->middleware('can:products.view');
    Route::resource('products', ProductController::class)->only(['create', 'store'])->middleware('can:products.create');
    Route::resource('products', ProductController::class)->only(['edit', 'update'])->middleware('can:products.update');

    Route::post('/products/{product}/toggle', [ProductController::class, 'toggle'])
        ->middleware(['can:products.deactivate'])->name('products.toggle');

    Route::get('/products/{product}/label', [ProductController::class, 'label'])
        ->middleware(['can:products.update'])->name('products.label');

    Route::post('/products/{product}/adjust', [ProductController::class, 'adjustStock'])
        ->middleware(['can:stock.adjust'])->name('products.adjust');

    // Categorias
    Route::resource('categories', CategoryController::class)->except(['create', 'edit', 'show'])->middleware('can:categories.manage');
    Route::post('/categories/{category}/toggle', [CategoryController::class, 'toggle'])
        ->middleware(['can:categories.manage'])->name('categories.toggle');

    // Relatórios extras
    Route::get('/reports/sales/summary', [ReportsController::class, 'salesSummary'])->middleware('can:reports.view')->name('reports.sales.summary');
    Route::get('/reports/sales/summary.csv', [ReportsController::class, 'salesSummaryCsv'])->middleware('can:reports.view')->name('reports.sales.summary.csv');

    Route::get('/reports/sales/products', [ReportsController::class, 'salesByProduct'])->middleware('can:reports.view')->name('reports.sales.products');
    Route::get('/reports/sales/products.csv', [ReportsController::class, 'salesByProductCsv'])->middleware('can:reports.view')->name('reports.sales.products.csv');

    Route::get('/reports/sales/categories', [ReportsController::class, 'salesByCategory'])->middleware('can:reports.view')->name('reports.sales.categories');
    Route::get('/reports/sales/categories.csv', [ReportsController::class, 'salesByCategoryCsv'])->middleware('can:reports.view')->name('reports.sales.categories.csv');

    Route::get('/reports/stock/history', [ReportsController::class, 'stockHistory'])->middleware('can:reports.view')->name('reports.stock.history');
    Route::get('/reports/stock/history.csv', [ReportsController::class, 'stockHistoryCsv'])->middleware('can:reports.view')->name('reports.stock.history.csv');

    // Relatório de clientes
    Route::get('/reports/customers', [ReportController::class, 'customers'])->middleware('can:reports.view')->name('reports.customers');
    Route::get('/reports/customers.csv', [ReportController::class, 'customersCsv'])->middleware('can:reports.view')->name('reports.customers.csv');

    // Pendente: revisar permissões de valores, cancelamento e finalização de OS.
    Route::middleware(['auth', 'role:admin|gerente|vendedor'])->group(function () {
        Route::get('/os', [ServiceOrderController::class, 'index'])->name('os.index');
        Route::get('/os/create', [ServiceOrderController::class, 'create'])->name('os.create');
        Route::post('/os', [ServiceOrderController::class, 'store'])->name('os.store');
        Route::get('/os/{os}/edit', [ServiceOrderController::class, 'edit'])->name('os.edit');
        Route::put('/os/{os}', [ServiceOrderController::class, 'update'])->name('os.update');

        Route::post('/os/{os}/items', [ServiceOrderController::class, 'addItem'])->name('os.items.add');
        Route::delete('/os/{os}/items/{item}', [ServiceOrderController::class, 'removeItem'])->name('os.items.remove');

        Route::post('/os/{os}/to-pos', [ServiceOrderController::class, 'toPos'])->name('os.to.pos');
    });
    /* ===================== Somente admin ===================== */
    Route::middleware('can:employees.manage')->group(function () {
        Route::resource('employees', EmployeeController::class)
            ->parameters(['employees' => 'employee'])
            ->except(['create', 'edit', 'show', 'destroy']);
        // Pendente: proteger autoinativação e preservar o último administrador ativo.
        Route::patch('/employees/{user}/deactivate', [EmployeeController::class, 'deactivate'])
            ->name('employees.deactivate');

        Route::patch('/employees/{user}/activate', [EmployeeController::class, 'activate'])
            ->name('employees.activate');

    });

});

Route::middleware('auth')->group(function () {
    Route::get('/meu-perfil', [ProfileController::class, 'edit'])
        ->name('profile.edit');

    Route::put('/meu-perfil', [ProfileController::class, 'update'])
        ->name('profile.update');
});
