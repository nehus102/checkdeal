<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\DemandeController;
use App\Http\Controllers\RetourController;
use App\Http\Middleware\EstAdmin;
use Illuminate\Support\Facades\Route;

Route::get('/', [DemandeController::class, 'accueil'])->name('accueil');
Route::post('/verifier', [DemandeController::class, 'store'])->middleware('throttle:10,60')->name('demande.store');
Route::get('/suivi/{token}', [DemandeController::class, 'suivi'])->name('demande.suivi');

Route::get('/retour/{token}/{choix}', [RetourController::class, 'show'])->name('retour.show');
Route::post('/retour/{token}', [RetourController::class, 'store'])->middleware('throttle:10,60')->name('retour.store');

Route::view('/confidentialite', 'confidentialite')->name('confidentialite');

Route::get('/admin/login', [AdminController::class, 'loginForm'])->name('admin.login');
Route::post('/admin/login', [AdminController::class, 'login'])->middleware('throttle:5,10');

Route::middleware(EstAdmin::class)->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminController::class, 'index'])->name('index');
    Route::post('/logout', [AdminController::class, 'logout'])->name('logout');
    Route::post('/temoin', [AdminController::class, 'temoin'])->name('temoin');
    Route::get('/annonces/{listing}', [AdminController::class, 'show'])->name('show');
    Route::post('/annonces/{listing}', [AdminController::class, 'evaluer'])->name('evaluer');
    Route::get('/preuves/{evidence}', [AdminController::class, 'preuve'])->name('preuve');
});
