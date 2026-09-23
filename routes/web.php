<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PublicOrderController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\PickupScheduleController;
use App\Http\Controllers\SupplyController;
use App\Http\Controllers\UserManagementController;
use Illuminate\Support\Facades\Route;

// Public buyer ordering. No customer account or payment route is exposed.
Route::get('/', [PublicOrderController::class, 'index'])->name('public.order.index');
Route::post('/order', [PublicOrderController::class, 'store'])
    ->middleware('throttle:public-orders')
    ->name('public.order.store');
Route::get('/order/success', [PublicOrderController::class, 'success'])->name('public.order.success');

// Authentication is limited to internal Owner and Assistant users.
Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.store');
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::resource('customers', CustomerController::class)->except(['destroy']);

    Route::get('/products', [ProductController::class, 'index'])->name('products.index');
    Route::post('/products', [ProductController::class, 'store'])->name('products.store');
    Route::match(['put', 'patch'], '/products/{product}', [ProductController::class, 'update'])->name('products.update');
    Route::patch('/products/{product}/toggle-status', [ProductController::class, 'toggleStatus'])->name('products.toggleStatus');

    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/create', [OrderController::class, 'create'])->name('orders.create');
    Route::post('/orders', [OrderController::class, 'store'])->name('orders.store');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::patch('/orders/{order}/details/{orderDetail}/price', [OrderController::class, 'updateDetailPrice'])->name('orders.updateDetailPrice');
    Route::patch('/orders/{order}/status', [OrderController::class, 'updateStatus'])->name('orders.updateStatus');
    Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');
    Route::post('/orders/{order}/images', [OrderController::class, 'attachImage'])->name('orders.attachImage');
    Route::post('/orders/{order}/payments', [PaymentController::class, 'store'])->name('orders.payments.store');

    Route::get('/supplies', [SupplyController::class, 'index'])->name('supplies.index');
    Route::post('/supplies', [SupplyController::class, 'store'])->name('supplies.store');
    Route::match(['put', 'patch'], '/supplies/{supply}', [SupplyController::class, 'update'])->name('supplies.update');
    Route::post('/supplies/{supply}/transactions', [SupplyController::class, 'recordTransaction'])->name('supplies.transactions.store');

    Route::get('/expenses', [ExpenseController::class, 'index'])->name('expenses.index');
    Route::post('/expenses', [ExpenseController::class, 'store'])->name('expenses.store');

    Route::get('/reports', [ReportsController::class, 'index'])->name('reports.index');
    Route::get('/pickup-schedule', [PickupScheduleController::class, 'index'])->name('schedule.index');

    Route::middleware(['can:manage-users', 'role:owner'])->group(function () {
        Route::get('/users', [UserManagementController::class, 'index'])->name('users.index');
        Route::get('/users/create', [UserManagementController::class, 'create'])->name('users.create');
        Route::post('/users', [UserManagementController::class, 'store'])->name('users.store');
        Route::get('/users/{user}/edit', [UserManagementController::class, 'edit'])->name('users.edit');
        Route::patch('/users/{user}', [UserManagementController::class, 'update'])->name('users.update');
    });
});
