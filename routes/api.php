<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\RetailController;

// Group Retail Analytics
Route::prefix('retail')->group(function () {
    
    // 1. Dashboard BI (Grafik)
    // URL: http://127.0.0.1:8000/api/retail/dashboard
    Route::get('/dashboard', [RetailController::class, 'dashboard']);

    // 2. List Produk
    // URL: http://127.0.0.1:8000/api/retail/products
    Route::get('/products', [RetailController::class, 'products']);

    // 3. Tambah Transaksi
    // URL: http://127.0.0.1:8000/api/retail/transaction (Method: POST)
    Route::post('/transaction', [RetailController::class, 'storeTransaction']);

});

// Auth bawaan Laravel (Biarkan saja default)
Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});