<?php

use App\Http\Controllers\ChatController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;

// Route pour la page d'accueil (chat)
Route::get('/', [ChatController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('home');

// Route pour envoyer un message au chat
Route::post('api/chat', [ChatController::class, 'sendMessage'])
    ->middleware(['auth', 'verified'])
    ->name('api.chat');

// Route pour récupérer les détails d'un modèle (limite de tokens, etc.)
Route::post('api/model-details', [ChatController::class, 'apiGetModelDetails'])
    ->middleware(['auth', 'verified'])
    ->name('api.model.details');

// Route pour récupérer l'historique des conversations
Route::get('api/conversation/{conversationId}', [ChatController::class, 'getConversationHistory'])
    ->middleware(['auth', 'verified'])
    ->name('api.conversation.history');

// Route pour récupérer la liste des conversations
Route::get('api/conversations', [ChatController::class, 'getConversations'])
    ->middleware(['auth', 'verified'])
    ->name('api.conversations');

// Route pour supprimer une conversation
Route::delete('api/conversation/{conversationId}', [ChatController::class, 'deleteConversation'])
    ->middleware(['auth', 'verified'])
    ->name('api.conversation.delete');

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

require __DIR__.'/auth.php';
