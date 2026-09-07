<?php

use App\Http\Controllers\ClientController;
use App\Http\Controllers\CourierBoardController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\OrderController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::get('board', [CourierBoardController::class, 'index'])->name('board.index');
    Route::get('board/{order}', [CourierBoardController::class, 'show'])
        ->middleware('can:view,order')->name('board.show');
    Route::post('board/{order}/status', [CourierBoardController::class, 'advanceStatus'])
        ->middleware('can:update,order')->name('board.status');

    Route::middleware('admin')->group(function () {
        Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
        Route::get('orders/create', [OrderController::class, 'create'])->name('orders.create');
        Route::post('orders', [OrderController::class, 'store'])->name('orders.store');
        Route::get('orders/{order}', [OrderController::class, 'show'])->name('orders.show');
        Route::post('orders/{order}/assign', [OrderController::class, 'assign'])->name('orders.assign');
        Route::post('orders/{order}/status', [OrderController::class, 'advanceStatus'])->name('orders.status');
        Route::post('orders/{order}/payment', [OrderController::class, 'registerPayment'])->name('orders.payment');

        Route::get('clients', [ClientController::class, 'index'])->name('clients.index');
        Route::get('clients/create', [ClientController::class, 'create'])->name('clients.create');
        Route::post('clients', [ClientController::class, 'store'])->name('clients.store');
    });
});

require __DIR__.'/settings.php';
