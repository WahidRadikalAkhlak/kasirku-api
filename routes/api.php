<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\TransactionController;

/*
|--------------------------------------------------------------------------
| Public Routes (Bisa diakses tanpa login)
|--------------------------------------------------------------------------
*/

// Endpoint tes koneksi dari Flutter
Route::get('/ping', function () {
    return response()->json([
        'status'  => 'ok',
        'message' => 'Server Kasirku Laragon Terhubung!',
    ]);
});

Route::get('/files/{path}', function ($path) {
    $fullPath = storage_path('app/public/' . $path);
    if (!file_exists($fullPath)) {
        abort(404);
    }
    return response()->file($fullPath);
})->where('path', '.*');

// Autentikasi Pengguna & Pendaftaran Toko
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);


/*
|--------------------------------------------------------------------------
| Protected Routes (Wajib Login / Bearer Token per Akun)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    // Cek profil user & toko yang sedang aktif login
    Route::get('/me', function (Request $request) {
        return response()->json([
            'status' => 'success',
            'user'   => $request->user(),
            'store'  => \App\Models\Store::where('user_id', $request->user()->id)->first(),
        ]);
    });

    // Logout (menghapus token sesi aktif)
    Route::post('/logout', function (Request $request) {
        $request->user()->currentAccessToken()->delete();
        return response()->json([
            'status'  => 'success',
            'message' => 'Berhasil keluar dari akun',
        ]);
    });

    // CRUD Produk / Menu (Otomatis terkunci per akun/toko)
    Route::get('/products', [ProductController::class, 'index']);
    Route::post('/products', [ProductController::class, 'store']);
    Route::put('/products/{id}', [ProductController::class, 'update']);
    Route::delete('/products/{id}', [ProductController::class, 'destroy']);

    Route::get('/transactions', [TransactionController::class, 'index']);
    Route::post('/transactions', [TransactionController::class, 'store']);


    Route::get('/transactions/{invoiceNumber}/status', [TransactionController::class, 'checkStatus']);

});
