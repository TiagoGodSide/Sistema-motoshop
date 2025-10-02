<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\{
    ProductController, PosController, OrderController, CategoryController, EmployeeController
};
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\CashRegisterController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';

Route::middleware(['auth'])->group(function () {
    Route::view('/dashboard', 'dashboard')->name('dashboard');

    Route::resource('products', ProductController::class);
    Route::get('products/{product}/label', [ProductController::class,'label'])->name('products.label');

    Route::resource('orders', OrderController::class)->only(['index','show','store']);
    Route::get('/pos', [PosController::class, 'index'])->name('pos.index');
    Route::post('/pos/add', [PosController::class, 'add']);     // adicionar item no carrinho
    Route::post('/pos/checkout', [PosController::class, 'checkout']);

    Route::resource('employees', EmployeeController::class);
    Route::resource('categories', CategoryController::class);
});

Route::middleware(['auth'])->group(function () {
    Route::get('/', fn() => redirect()->route('pos.index'));

    // Produtos
    Route::resource('products', ProductController::class);
    Route::get('products/{product}/label', [ProductController::class,'label'])->name('products.label');
    Route::post('products/{product}/activate', [ProductController::class,'activate'])->name('products.activate');
    Route::get('products-search', [ProductController::class,'search'])->name('products.search');

    // Categorias
    Route::resource('categories', CategoryController::class)->except(['create','edit','show']);

    // Funcionários (users)
    Route::resource('employees', EmployeeController::class)->parameters(['employees' => 'employee'])->except(['create','edit','show']);

    // PDV
    Route::get('/pos', [PosController::class, 'index'])->name('pos.index');
    Route::get('/pos/find', [PosController::class, 'find'])->name('pos.find');
    Route::post('/pos/checkout', [PosController::class, 'checkout'])->name('pos.checkout');

    // Pedidos
    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');
});

Route::middleware(['auth'])->get('/reports/sales',[ReportsController::class,'sales'])->name('reports.sales');

// routes/web.php
Route::middleware(['auth'])->group(function () {
    Route::get('/reports/stock', [\App\Http\Controllers\ReportsController::class,'stock'])->name('reports.stock');
});

Route::middleware(['auth'])->group(function () {
    // PDV
    Route::get('/pos', [PosController::class,'index'])->name('pos.index');
    Route::get('/pos/find', [PosController::class,'find'])->name('pos.find');
    Route::post('/pos/checkout', [PosController::class,'checkout'])->name('pos.checkout');

    // Pedidos
    Route::get('/orders/{order}/receipt', [OrderController::class,'receipt'])->name('orders.receipt');
    Route::post('/orders/{order}/finalize', [OrderController::class,'finalize'])->name('orders.finalize');

    // Caixa
    Route::get('/cash', [CashRegisterController::class,'index'])->name('cash.index');
    Route::post('/cash/open', [CashRegisterController::class,'open'])->name('cash.open');
    Route::post('/cash/close', [CashRegisterController::class,'close'])->name('cash.close');
    Route::post('/cash/movement', [CashRegisterController::class,'movement'])->name('cash.movement');
});