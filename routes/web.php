<?php

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

// Redirection des anciennes routes vers la page d'accueil
Route::redirect('dashboard', '/')->name('dashboard');
Route::redirect('chat', '/');

// Redirection vers la page de login pour éviter l'erreur 404 après expiration de session
Route::redirect('login', '/login')->name('login');

require __DIR__.'/auth.php';
