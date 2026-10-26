<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});
Route::get('/app', fn () => view('patient'))->name('patient.app');
Route::get('/mitra', fn () => view('mitra'))->name('partner.app');
Route::get('/hapus-akun', fn () => view('hapus-akun'))->name('account.deletion');
