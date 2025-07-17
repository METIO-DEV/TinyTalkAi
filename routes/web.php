<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ChatController;

Route::view('/', 'welcome'); // Route pour la page d'accueil

Route::view('dashboard', 'dashboard') // Route pour la page dashboard 
    ->middleware(['auth', 'verified']) // Authentification requise
    ->name('dashboard'); // Nom de la route

Route::get('chat', [ChatController::class, 'index']) // Route pour la page chat
    ->middleware(['auth', 'verified']) // Authentification requise
    ->name('chat'); // Nom de la route

// Route pour envoyer un message au chat
Route::post('chat/send', [ChatController::class, 'sendMessage']) // Route pour envoyer un message au chat
    ->middleware(['auth', 'verified']) // Authentification requise
    ->name('chat.send'); // Nom de la route

Route::view('profile', 'profile') // Route pour la page profile
    ->middleware(['auth']) // Authentification requise
    ->name('profile'); // Nom de la route

require __DIR__.'/auth.php';
