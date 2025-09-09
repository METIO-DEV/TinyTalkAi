<?php

use App\Http\Controllers\ChatStreamController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;

// Route pour la page d'accueil (chat) - directement vers la vue sans contrôleur
Route::view('/', 'chat')
    ->middleware(['auth', 'verified'])
    ->name('home');

// Route pour la page profile
Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

// Route pour la suppression du compte utilisateur
Route::delete('/profile', function () {
    $user = Auth::user();

    if (! Hash::check(request('password'), $user->password)) {
        return back()->withErrors(['password' => __('This password does not match our records.')], 'userDeletion');
    }

    Auth::logout();
    $user->delete();

    request()->session()->invalidate();
    request()->session()->regenerateToken();

    return redirect('/');
})->middleware(['auth'])->name('profile.destroy');

// Route pour la déconnexion
Route::post('/logout', function () {
    Auth::logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();

    return redirect('/');
})->middleware('auth')->name('logout');

// Route pour changer la langue (fr/en)
Route::post('/locale', function () {
    $locale = request('locale');
    if (! in_array($locale, ['en', 'fr'], true)) {
        $locale = config('app.locale');
    }
    session()->put('locale', $locale);

    return back();
})->name('locale.set');

// Route API pour le streaming chat
Route::post('/api/chat/stream', [ChatStreamController::class, 'stream'])
    ->middleware(['auth', \App\Http\Middleware\SetLocale::class])
    ->name('chat.stream');

// Redirection des anciennes routes vers la page d'accueil
Route::redirect('dashboard', '/')->name('dashboard');
Route::redirect('chat', '/');

// Redirection vers la page de login pour éviter l'erreur 404 après expiration de session
Route::redirect('login', '/login')->name('login');

// Ne pas définir de routes personnalisées pour Filament ici
// Filament gère ses propres routes via son système interne

require __DIR__.'/auth.php';
