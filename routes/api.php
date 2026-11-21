<?php

use App\Http\Controllers\ExtensionController;
use Illuminate\Support\Facades\Route;

// Préfixe /api ajouté par bootstrap/app.php. CORS ouvert (défaut Laravel sur
// api/*) : l'appelant est une extension, sans cookie ni session.
Route::post('/extension/verifier', [ExtensionController::class, 'verifier'])
    ->middleware('throttle:10,60')
    ->name('extension.verifier');
