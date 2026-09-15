<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\OrderItemController;

Route::get('/', function () {
    return view('welcome');
});

// categories
Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
Route::get('/categories/create', [CategoryController::class, 'create'])->name('categories.create');
Route::post('/categories/store', [CategoryController::class, 'store'])->name('categories.store');
Route::get('/categories/edit/{id}', [CategoryController::class, 'edit'])->name('categories.edit');
Route::post('/categories/update/{id}', [CategoryController::class, 'update'])->name('categories.update');
Route::delete('/categories/delete/{id}', [CategoryController::class, 'destroy'])->name('categories.delete');
Route::get('/categories/{id}', [CategoryController::class, 'show'])->name('categories.show');

// products
Route::get('/products', [ProductController::class, 'index'])->name('products.index');
Route::get('/products/create', [ProductController::class, 'create'])->name('products.create');
Route::post('/products/store', [ProductController::class, 'store'])->name('products.store');
Route::get('/products/edit/{id}', [ProductController::class, 'edit'])->name('products.edit');
Route::post('/products/update/{id}', [ProductController::class, 'update'])->name('products.update');
Route::delete('/products/delete/{id}', [ProductController::class, 'destroy'])->name('products.delete');
Route::get('/products/{id}', [ProductController::class, 'show'])->name('products.show');

// orders
Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
Route::get('/orders/create', [OrderController::class, 'create'])->name('orders.create');
Route::post('/orders/store', [OrderController::class, 'store'])->name('orders.store');
Route::get('/orders/edit/{id}', [OrderController::class, 'edit'])->name('orders.edit');
Route::post('/orders/update/{id}', [OrderController::class, 'update'])->name('orders.update');
Route::delete('/orders/delete/{id}', [OrderController::class, 'destroy'])->name('orders.delete');
Route::get('/orders/{id}', [OrderController::class, 'show'])->name('orders.show');

// orderItem
Route::get('/order-items', [OrderItemController::class, 'index'])->name('order-items.index');
Route::get('/order-items/create', [OrderItemController::class, 'create'])->name('order-items.create');
Route::post('/order-items/store', [OrderItemController::class, 'store'])->name('order-items.store');
Route::get('/order-items/edit/{id}', [OrderItemController::class, 'edit'])->name('order-items.edit');
Route::post('/order-items/update/{id}', [OrderItemController::class, 'update'])->name('order-items.update');
Route::delete('/order-items/delete/{id}', [OrderItemController::class, 'destroy'])->name('order-items.delete');
Route::get('/order-items/{id}', [OrderItemController::class, 'show'])->name('order-items.show');
