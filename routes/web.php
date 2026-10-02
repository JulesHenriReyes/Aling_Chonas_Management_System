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
use App\Http\Controllers\OrderPaymentPageController;
use App\Http\Controllers\PaymentReviewController;
use App\Http\Controllers\RefundController;
use App\Http\Controllers\CatalogController;
use App\Http\Middleware\PrivateOrderResponse;
use Illuminate\Support\Facades\Route;

// Private order links are bearer credentials, never sequential order IDs.
Route::get('/', [PublicOrderController::class, 'index'])->name('public.order.index');
Route::get('/packages/{product}/customize/{line}', [PublicOrderController::class, 'customize'])->middleware(PrivateOrderResponse::class)->block()->name('public.package.customize');
Route::post('/packages/{product}/customize/{line}', [PublicOrderController::class, 'savePackage'])->middleware(PrivateOrderResponse::class)->block()->name('public.package.save');
Route::post('/packages/{product}/draft/{line}', [PublicOrderController::class, 'saveEditor'])->middleware(['throttle:60,1', PrivateOrderResponse::class])->block()->name('public.package.draft');
Route::post('/order/packages/{line}/remove', [PublicOrderController::class, 'removePackage'])->block()->name('public.package.remove');
Route::post('/order', [PublicOrderController::class, 'store'])
    ->middleware('throttle:public-orders')
    ->block()
    ->name('public.order.store');
Route::get('/order/success', [PublicOrderController::class, 'success'])->name('public.order.success');
Route::post('/order/quote', [PublicOrderController::class, 'quote'])->middleware('throttle:public-quotes')->name('public.order.quote');
Route::post('/order/details', [PublicOrderController::class, 'saveSelection'])->middleware('throttle:public-orders')->name('public.order.continue');
Route::get('/order/details', [PublicOrderController::class, 'details'])->name('public.order.details');
Route::post('/order/details/back', [PublicOrderController::class, 'backToSelection'])->name('public.order.back');
Route::middleware(PrivateOrderResponse::class)->prefix('/order/payment/{token}')->where(['token' => '[a-f0-9]{64}'])->group(function () {
    Route::get('/', [OrderPaymentPageController::class, 'show'])->name('public.order.payment');
    Route::post('/receipt', [OrderPaymentPageController::class, 'submit'])->middleware('throttle:public-receipts')->name('public.order.receipt');
    Route::get('/qr', [OrderPaymentPageController::class, 'qr'])->name('public.order.qr');
    Route::get('/save-link', [OrderPaymentPageController::class, 'saveLink'])->name('public.order.saveLink');
});

// Authentication is limited to internal Owner and Assistant users.
Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.store');
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware(['auth', 'role:owner,assistant'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::resource('customers', CustomerController::class)->except(['destroy']);

    Route::get('/products', [CatalogController::class, 'index'])->name('products.index');
    Route::middleware('role:owner')->group(function () {
        Route::get('/products/create', [CatalogController::class, 'editProduct'])->name('products.create');
        Route::get('/products/{product}/edit', [CatalogController::class, 'editProduct'])->name('products.edit');
        Route::post('/products', [CatalogController::class, 'saveProduct'])->name('products.store');
        Route::match(['put', 'patch'], '/products/{product}', [CatalogController::class, 'saveProduct'])->name('products.update');
        Route::patch('/products/{product}/toggle-status', [CatalogController::class, 'toggleProduct'])->name('products.toggleStatus');
        Route::post('/products/{product}/options', [CatalogController::class, 'saveOption'])->name('options.store');
        Route::patch('/products/{product}/options/{option}', [CatalogController::class, 'saveOption'])->name('options.update');
        Route::get('/add-ons/create', [CatalogController::class, 'editAddOn'])->name('add-ons.create');
        Route::get('/add-ons/{addOn}/edit', [CatalogController::class, 'editAddOn'])->name('add-ons.edit');
        Route::post('/add-ons', [CatalogController::class, 'saveAddOn'])->name('add-ons.store');
        Route::patch('/add-ons/{addOn}', [CatalogController::class, 'saveAddOn'])->name('add-ons.update');
        Route::get('/payment-settings', [CatalogController::class, 'settings'])->name('payment-settings.edit');
        Route::post('/payment-settings', [CatalogController::class, 'saveSettings'])->name('payment-settings.update');
    });

    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/create', [OrderController::class, 'create'])->name('orders.create');
    Route::post('/orders/create/details', [OrderController::class, 'saveSelection'])->name('orders.continue');
    Route::get('/orders/create/details', [OrderController::class, 'details'])->name('orders.details');
    Route::post('/orders/create/details/back', [OrderController::class, 'backToSelection'])->name('orders.back');
    Route::post('/orders/create/customer', [OrderController::class, 'inlineCustomer'])->name('orders.inlineCustomer');
    Route::post('/orders', [OrderController::class, 'store'])->name('orders.store');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::patch('/orders/{order}/status', [OrderController::class, 'updateStatus'])->name('orders.updateStatus');
    Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');
    Route::post('/orders/{order}/images', [OrderController::class, 'attachImage'])->name('orders.attachImage');
    Route::post('/orders/{order}/payments', [PaymentController::class, 'store'])->name('orders.payments.store');
    Route::get('/payment-proofs/{proof}/receipt', [PaymentReviewController::class, 'receipt'])->middleware(PrivateOrderResponse::class)->name('proofs.receipt');
    Route::post('/payment-proofs/{proof}/accept', [PaymentReviewController::class, 'accept'])->name('proofs.accept');
    Route::post('/payment-proofs/{proof}/reject', [PaymentReviewController::class, 'reject'])->name('proofs.reject');
    Route::post('/orders/{order}/bakery-failure', [RefundController::class, 'store'])->name('orders.bakeryFailure');
    Route::post('/refunds/{refund}/complete', [RefundController::class, 'complete'])->name('refunds.complete');

    Route::get('/supplies', [SupplyController::class, 'index'])->name('supplies.index');
    Route::get('/supplies/create', [SupplyController::class, 'create'])->name('supplies.create');
    Route::get('/supplies/lookup', [SupplyController::class, 'lookup'])->name('supplies.lookup');
    Route::get('/supplies/{supply}/edit', [SupplyController::class, 'edit'])->name('supplies.edit');
    Route::get('/supplies/{supply}', [SupplyController::class, 'show'])->name('supplies.show');
    Route::get('/inventory/history', [SupplyController::class, 'history'])->name('inventory.history');
    Route::get('/inventory/create/{type}', [SupplyController::class, 'operationForm'])->name('inventory.create');
    Route::get('/inventory/movements/{movement}', [SupplyController::class, 'legacyMovement'])->name('inventory.movement');
    Route::post('/inventory/movements/{movement}/reverse', [SupplyController::class, 'reverseLegacy'])->name('inventory.movement.reverse');
    Route::post('/inventory', [SupplyController::class, 'postOperation'])->name('inventory.store');
    Route::get('/inventory/{operation}', [SupplyController::class, 'operation'])->name('inventory.show');
    Route::post('/inventory/{operation}/reverse', [SupplyController::class, 'reverse'])->name('inventory.reverse');
    Route::post('/supplies', [SupplyController::class, 'store'])->name('supplies.store');
    Route::match(['put', 'patch'], '/supplies/{supply}', [SupplyController::class, 'update'])->name('supplies.update');
    Route::post('/supplies/{supply}/transactions', [SupplyController::class, 'recordTransaction'])->name('supplies.transactions.store');

    Route::get('/expenses', [ExpenseController::class, 'index'])->name('expenses.index');
    Route::get('/expenses/create', [ExpenseController::class, 'create'])->name('expenses.create');
    Route::get('/expenses/history', [ExpenseController::class, 'history'])->name('expenses.history');
    Route::get('/expenses/{expense}', [ExpenseController::class, 'show'])->name('expenses.show');
    Route::get('/expenses/{expense}/edit', [ExpenseController::class, 'edit'])->name('expenses.edit');
    Route::patch('/expenses/{expense}', [ExpenseController::class, 'update'])->name('expenses.update');
    Route::delete('/expenses/{expense}', [ExpenseController::class, 'destroy'])->name('expenses.destroy');
    Route::post('/expenses', [ExpenseController::class, 'store'])->name('expenses.store');

    Route::get('/reports', [ReportsController::class, 'index'])->name('reports.index');
    Route::get('/reports/records', [ReportsController::class, 'records'])->name('reports.records');
    Route::get('/reports/export', [ReportsController::class, 'export'])->name('reports.export');
    Route::get('/pickup-schedule', [PickupScheduleController::class, 'index'])->name('schedule.index');

    Route::middleware(['can:manage-users', 'role:owner'])->group(function () {
        Route::get('/users', [UserManagementController::class, 'index'])->name('users.index');
        Route::get('/users/create', [UserManagementController::class, 'create'])->name('users.create');
        Route::post('/users', [UserManagementController::class, 'store'])->name('users.store');
        Route::get('/users/{user}/edit', [UserManagementController::class, 'edit'])->name('users.edit');
        Route::patch('/users/{user}', [UserManagementController::class, 'update'])->name('users.update');
    });
});
