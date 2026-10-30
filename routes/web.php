<?php

use App\Http\Controllers\MonikaController as M;
use App\Http\Middleware\ActiveAccount;
use Illuminate\Support\Facades\Route;

Route::view('/', 'home')->name('home');
Route::view('/pengajuan', 'apply');
Route::post('/pengajuan', [M::class, 'apply'])->middleware('throttle:6,1');
Route::middleware('guest')->group(function () {
    Route::view('/login', 'login')->name('login');
    Route::post('/login', [M::class, 'login'])->middleware('throttle:5,1');
});
Route::middleware(['auth', ActiveAccount::class])->group(function () {
    Route::post('/logout', [M::class, 'logout']);
    Route::get('/dashboard', [M::class, 'dashboard']);
    Route::get('/pengguna', [M::class, 'users']);
    Route::post('/pengguna', [M::class, 'saveUser']);
    Route::put('/pengguna/{user}', [M::class, 'saveUser']);
    Route::delete('/pengguna/{user}', [M::class, 'deleteUser']);
    Route::get('/kelompok', [M::class, 'groups']);
    Route::post('/kelompok', [M::class, 'saveGroup']);
    Route::put('/kelompok/{id}', [M::class, 'saveGroup']);
    Route::delete('/kelompok/{id}', [M::class, 'deleteGroup']);
    Route::get('/admin/pengajuan', [M::class, 'applications']);
    Route::put('/admin/pengajuan/{id}', [M::class, 'reviewApplication']);
    Route::get('/admin/pengajuan/{id}/surat', [M::class, 'letter']);
    Route::get('/laporan', [M::class, 'report']);
    Route::get('/{kind}', [M::class, 'records'])->where('kind', 'absensi|logbook');
    Route::post('/{kind}', [M::class, 'storeRecord'])->where('kind', 'absensi|logbook');
    Route::put('/{kind}/{id}/validasi', [M::class, 'reviewRecord'])->where('kind','absensi|logbook');
});
