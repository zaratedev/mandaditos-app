<?php

use App\Http\Controllers\ClientController;
use App\Http\Controllers\CourierBoardController;
use App\Http\Controllers\CourierController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::get('board', [CourierBoardController::class, 'index'])->name('board.index');
    Route::get('board/{order}', [CourierBoardController::class, 'show'])
        ->middleware('can:view,order')->name('board.show');
    Route::post('board/{order}/status', [CourierBoardController::class, 'advanceStatus'])
        ->middleware('can:update,order')->name('board.status');

    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::post('notifications/{id}/read', [NotificationController::class, 'read'])->name('notifications.read');

    Route::post('push-subscriptions', [PushSubscriptionController::class, 'store'])->name('push-subscriptions.store');
    Route::delete('push-subscriptions', [PushSubscriptionController::class, 'destroy'])->name('push-subscriptions.destroy');

    Route::middleware('admin')->group(function () {
        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');

        Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
        Route::get('orders/create', [OrderController::class, 'create'])->name('orders.create');
        Route::post('orders', [OrderController::class, 'store'])->name('orders.store');
        Route::get('orders/{order}/edit', [OrderController::class, 'edit'])->name('orders.edit');
        Route::get('orders/{order}', [OrderController::class, 'show'])->name('orders.show');
        Route::put('orders/{order}', [OrderController::class, 'update'])->name('orders.update');
        Route::post('orders/{order}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');
        Route::post('orders/{order}/assign', [OrderController::class, 'assign'])->name('orders.assign');
        Route::post('orders/{order}/status', [OrderController::class, 'advanceStatus'])->name('orders.status');
        Route::post('orders/{order}/purchase', [OrderController::class, 'recordPurchase'])->name('orders.purchase');
        Route::post('orders/{order}/payment', [OrderController::class, 'registerPayment'])->name('orders.payment');

        Route::get('couriers', [CourierController::class, 'index'])->name('couriers.index');
        Route::get('couriers/create', [CourierController::class, 'create'])->name('couriers.create');
        Route::post('couriers', [CourierController::class, 'store'])->name('couriers.store');
        Route::get('couriers/{courier}/edit', [CourierController::class, 'edit'])->name('couriers.edit');
        Route::put('couriers/{courier}', [CourierController::class, 'update'])->name('couriers.update');
        Route::post('couriers/{courier}/activate', [CourierController::class, 'activate'])->name('couriers.activate');
        Route::post('couriers/{courier}/deactivate', [CourierController::class, 'deactivate'])->name('couriers.deactivate');

        Route::get('clients', [ClientController::class, 'index'])->name('clients.index');
        Route::get('clients/create', [ClientController::class, 'create'])->name('clients.create');
        Route::post('clients', [ClientController::class, 'store'])->name('clients.store');
        Route::get('clients/{client}/edit', [ClientController::class, 'edit'])->name('clients.edit');
        Route::put('clients/{client}', [ClientController::class, 'update'])->name('clients.update');
        Route::delete('clients/{client}', [ClientController::class, 'destroy'])->name('clients.destroy');
        Route::post('clients/{client}/archive', [ClientController::class, 'archive'])->name('clients.archive');
        Route::post('clients/{client}/restore', [ClientController::class, 'restore'])->name('clients.restore');
    });
});

require __DIR__.'/settings.php';
