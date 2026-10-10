<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\OrderPaymentPageController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PaymentReviewController;
use App\Http\Controllers\PickupScheduleController;
use App\Http\Controllers\PublicOrderController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\SupplyController;
use App\Http\Controllers\UserManagementController;
use App\Http\Middleware\PrivateOrderResponse;
use Illuminate\Support\Facades\Route;

// Private order links are bearer credentials, never sequential order IDs.
Route::get('/', [PublicOrderController::class, 'index'])->name('public.order.index');
Route::get('/packages/{product}/customize/{line}', [PublicOrderController::class, 'customize'])->middleware(PrivateOrderResponse::class)->block()->name('public.package.customize');
Route::post('/packages/{product}/customize/{line}', [PublicOrderController::class, 'savePackage'])->middleware(PrivateOrderResponse::class)->block()->name('public.package.save');
Route::post('/packages/{product}/draft/{line}', [PublicOrderController::class, 'saveEditor'])->middleware(['throttle:60,1', PrivateOrderResponse::class])->block()->name('public.package.draft');
Route::post('/order/packages/{line}/remove', [PublicOrderController::class, 'removePackage'])->block()->name('public.package.remove');
Route::get('/storage/order_drafts/{session_id}/{filename}', [PublicOrderController::class, 'draftImage'])->where(['session_id' => '[A-Za-z0-9_\-]+', 'filename' => '[A-Za-z0-9_\-\.]+'])->middleware(PrivateOrderResponse::class);
Route::get('/order_drafts/{session_id}/{filename}', [PublicOrderController::class, 'draftImage'])->where(['session_id' => '[A-Za-z0-9_\-]+', 'filename' => '[A-Za-z0-9_\-\.]+'])->middleware(PrivateOrderResponse::class)->name('public.draft.image');
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
    Route::post('/cancel', [OrderPaymentPageController::class, 'cancel'])->middleware('throttle:10,1')->name('public.order.cancel');
    Route::get('/qr', [OrderPaymentPageController::class, 'qr'])->name('public.order.qr');
    Route::get('/save-link', [OrderPaymentPageController::class, 'saveLink'])->name('public.order.saveLink');
});

// Authentication is limited to internal Owner and Assistant users.
Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.store');
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware(['auth', 'role:owner,assistant'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::resource('customers', CustomerController::class)->only(['create', 'store', 'edit', 'update'])->middleware('can:manage-customers');
    Route::resource('customers', CustomerController::class)->only(['index', 'show']);

    Route::get('/products', [CatalogController::class, 'index'])->name('products.index');
    Route::middleware('role:owner')->group(function () {
        Route::get('/products/create', [CatalogController::class, 'editProduct'])->name('products.create');
        Route::get('/products/{product}/edit', [CatalogController::class, 'editProduct'])->name('products.edit');
        Route::post('/products', [CatalogController::class, 'saveProduct'])->name('products.store');
        Route::match(['put', 'patch'], '/products/{product}', [CatalogController::class, 'saveProduct'])->name('products.update');
        Route::patch('/products/{product}/toggle-status', [CatalogController::class, 'toggleProduct'])->name('products.toggleStatus');
        Route::post('/products/{product}/options', [CatalogController::class, 'saveOption'])->name('options.store');
        Route::patch('/products/{product}/options/{option}', [CatalogController::class, 'saveOption'])->name('options.update');
        Route::patch('/products/{product}/options/{option}/availability', [CatalogController::class, 'toggleOption'])->name('options.toggle');
        Route::get('/add-ons/create', [CatalogController::class, 'editAddOn'])->name('add-ons.create');
        Route::get('/add-ons/{addOn}/edit', [CatalogController::class, 'editAddOn'])->name('add-ons.edit');
        Route::post('/add-ons', [CatalogController::class, 'saveAddOn'])->name('add-ons.store');
        Route::patch('/add-ons/{addOn}', [CatalogController::class, 'saveAddOn'])->name('add-ons.update');
        Route::get('/payment-settings', [CatalogController::class, 'settings'])->name('payment-settings.edit');
        Route::post('/payment-settings', [CatalogController::class, 'saveSettings'])->name('payment-settings.update');
    });

    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::middleware(['role:owner', 'can:manage-orders', PrivateOrderResponse::class])->group(function () {
        Route::get('/orders/create/packages/{product}/customize/{line}', [OrderController::class, 'customize'])->block()->name('orders.package.customize');
        Route::post('/orders/create/packages/{product}/customize/{line}', [OrderController::class, 'savePackage'])->block()->name('orders.package.save');
        Route::post('/orders/create/packages/{product}/draft/{line}', [OrderController::class, 'saveEditor'])->middleware('throttle:60,1')->block()->name('orders.package.draft');
        Route::post('/orders/create/packages/{line}/remove', [OrderController::class, 'removePackage'])->block()->name('orders.package.remove');
        Route::get('/orders/create/references/{draft}/{line}/{image}', [OrderController::class, 'draftImage'])->whereUuid(['draft', 'line', 'image'])->name('orders.package.image');
        Route::post('/orders/create/quote', [OrderController::class, 'quote'])->middleware('throttle:60,1')->name('orders.package.quote');
        Route::post('/orders/create/details/draft', [OrderController::class, 'saveDetailsDraft'])->middleware('throttle:60,1')->block()->name('orders.details.draft');
    });
    Route::get('/orders/create', [OrderController::class, 'create'])->middleware(['can:manage-orders', PrivateOrderResponse::class])->block()->name('orders.create');
    Route::post('/orders/create/details', [OrderController::class, 'saveSelection'])->middleware(['can:manage-orders', PrivateOrderResponse::class])->block()->name('orders.continue');
    Route::get('/orders/create/details', [OrderController::class, 'details'])->middleware(['can:manage-orders', PrivateOrderResponse::class])->block()->name('orders.details');
    Route::post('/orders/create/details/back', [OrderController::class, 'backToSelection'])->middleware(['can:manage-orders', PrivateOrderResponse::class])->block()->name('orders.back');
    Route::post('/orders/create/customer', [OrderController::class, 'inlineCustomer'])->middleware('can:manage-customers')->name('orders.inlineCustomer');
    Route::post('/orders', [OrderController::class, 'store'])->middleware('can:manage-orders')->block()->name('orders.store');
    Route::get('/orders/{order}/references/{image}', [OrderController::class, 'referenceImage'])->middleware(PrivateOrderResponse::class)->name('orders.images.show');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::patch('/orders/{order}/status', [OrderController::class, 'updateStatus'])->middleware('can:update-order-status')->name('orders.updateStatus');
    Route::post('/orders/{order}/confirm', [OrderController::class, 'confirm'])->middleware('can:confirm-orders')->name('orders.confirm');
    Route::post('/orders/{order}/decline', [OrderController::class, 'decline'])->middleware('can:decline-orders')->name('orders.decline');
    Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel'])->middleware('can:cancel-orders')->name('orders.cancel');
    Route::post('/orders/{order}/images', [OrderController::class, 'attachImage'])->middleware('can:manage-orders')->name('orders.attachImage');
    Route::post('/orders/{order}/payments', [PaymentController::class, 'store'])->middleware('can:record-payments')->name('orders.payments.store');
    Route::post('/orders/{order}/complete-pickup', [PaymentController::class, 'completePickup'])->middleware('can:record-payments')->name('orders.completePickup');
    Route::get('/payment-proofs/{proof}/receipt', [PaymentReviewController::class, 'receipt'])->middleware(PrivateOrderResponse::class)->name('proofs.receipt');
    Route::post('/payment-proofs/{proof}/accept', [PaymentReviewController::class, 'accept'])->middleware('can:review-proofs')->name('proofs.accept');
    Route::post('/payment-proofs/{proof}/reject', [PaymentReviewController::class, 'reject'])->middleware('can:review-proofs')->name('proofs.reject');

    Route::get('/supplies', [SupplyController::class, 'index'])->name('supplies.index');
    Route::get('/supplies/create', [SupplyController::class, 'create'])->name('supplies.create');
    Route::get('/supplies/lookup', [SupplyController::class, 'lookup'])->name('supplies.lookup');
    Route::get('/supplies/{supply}/edit', [SupplyController::class, 'edit'])->name('supplies.edit');
    Route::get('/supplies/{supply}', [SupplyController::class, 'show'])->name('supplies.show');
    Route::get('/inventory/history', [SupplyController::class, 'history'])->name('inventory.history');
    Route::post('/inventory/preview', [SupplyController::class, 'preview'])->name('inventory.preview');
    Route::get('/inventory/verify-opening', [SupplyController::class, 'verifyForm'])->name('inventory.verify');
    Route::post('/inventory/verify-opening', [SupplyController::class, 'verifyOpening'])->name('inventory.verify.store');
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

    Route::get('/reports', [ReportsController::class, 'index'])->middleware('can:view-reports')->name('reports.index');
    Route::get('/reports/records', [ReportsController::class, 'records'])->middleware('can:view-reports')->name('reports.records');
    Route::get('/reports/export', [ReportsController::class, 'export'])->middleware('can:view-reports')->name('reports.export');
    Route::get('/pickup-schedule', [PickupScheduleController::class, 'index'])->name('schedule.index');

    Route::middleware(['can:manage-users', 'role:owner'])->group(function () {
        Route::get('/users', [UserManagementController::class, 'index'])->name('users.index');
        Route::get('/users/create', [UserManagementController::class, 'create'])->name('users.create');
        Route::post('/users', [UserManagementController::class, 'store'])->name('users.store');
        Route::get('/users/{user}/edit', [UserManagementController::class, 'edit'])->name('users.edit');
        Route::patch('/users/{user}', [UserManagementController::class, 'update'])->name('users.update');
    });
});
