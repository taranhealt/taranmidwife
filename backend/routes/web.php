<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('beranda');
});
Route::get('/app', fn () => view('patient'))->name('patient.app');
Route::get('/mitra', fn () => view('mitra'))->name('partner.app');
Route::get('/hapus-akun', fn () => view('hapus-akun'))->name('account.deletion');
Route::get('/lupa-sandi', fn () => view('lupa-sandi'))->name('password.forgot');
Route::get('/atur-ulang-sandi', fn () => view('atur-ulang-sandi'))->name('password.reset.page');
Route::get('/kebijakan-privasi', fn () => view('kebijakan-privasi'))->name('privacy.policy');
Route::get('/admin', fn () => view('admin'))->name('admin.app');
