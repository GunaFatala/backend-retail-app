<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\RetailController;
use App\Http\Controllers\Api\AuthController;

// Group Retail Analytics
// Semua route di dalam sini otomatis punya awalan: /api/retail/....
Route::prefix('retail')->group(function () {
    
    // 1. Dashboard BI (Grafik)
    // URL: http://127.0.0.1:8000/api/retail/dashboard
    Route::get('/dashboard', [RetailController::class, 'dashboard']);

    // 2. List Produk
    // URL: http://127.0.0.1:8000/api/retail/products
    Route::get('/products', [RetailController::class, 'products']);

    // 3. Tambah Transaksi
    // URL: http://127.0.0.1:8000/api/retail/transaction
    Route::post('/transaction', [RetailController::class, 'storeTransaction']);

    // 4. AUTH (Saya pindahkan ke sini sesuai request)
    // URL: http://127.0.0.1:8000/api/retail/register
    Route::post('/register', [AuthController::class, 'register']);
    
    // URL: http://127.0.0.1:8000/api/retail/login
    Route::post('/login', [AuthController::class, 'login']);

});

// Auth bawaan Laravel (Biarkan saja default)
Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});