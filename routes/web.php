<?php

use App\Models\Order;
use App\Models\Tenant;
use App\Services\DokuService;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/test-checkout', function () {
    $tenant = Tenant::find(2);

    $order = Order::find(25);

    $service = new DokuService($tenant);

    dd($service->dokuCheckout($order));
});
