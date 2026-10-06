<?php

use App\Http\Controllers\QrOrderController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/order/{tenant:slug}/{token}', [QrOrderController::class, 'scan'])->name('qr.scan');

Route::get('/order/{shop}', function () {
    if (!session()->has('active_qr_token')) {
        abort(404);
    }

    return view('mobile');
})->name('mobile.menu');

Route::get('/api/mobile/profile', [QrOrderController::class, 'getStoreProfile']);
Route::get('/api/mobile/products', [QrOrderController::class, 'getProducts']);
Route::post('/api/mobile/orders', [QrOrderController::class, 'submitOrder'])->name('mobile.api.order');
Route::get('/api/mobile/orders/{receiptNumber}/status', [QrOrderController::class, 'checkStatus']);
