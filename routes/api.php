<?php

use App\Http\Controllers\ExtensionController;
use Illuminate\Support\Facades\Route;

// Préfixe /api ajouté par bootstrap/app.php. CORS ouvert (défaut Laravel sur
// api/*) : l'appelant est une extension, sans cookie ni session.
Route::post('/extension/verifier', [ExtensionController::class, 'verifier'])
    ->middleware('throttle:10,60')
    ->name('extension.verifier');

// Chaque appel coûte jusqu'à 5 requêtes SerpApi : 20 appels par heure et par IP.
Route::post('/extension/photos', [ExtensionController::class, 'photos'])
    ->middleware('throttle:20,60')
    ->name('extension.photos');
