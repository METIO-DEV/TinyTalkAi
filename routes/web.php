<?php

use App\Http\Controllers\ChatAppController;
use App\Http\Controllers\ChatStreamController;
use App\Http\Middleware\SetLocale;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;

// Route pour la page d'accueil (chat) - interface React/Inertia
Route::get('/', [ChatAppController::class, 'index'])
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
    ->middleware(['auth', SetLocale::class])
    ->name('chat.stream');

Route::middleware(['auth', SetLocale::class])->prefix('/api/chat')->group(function () {
    Route::get('/state', [ChatAppController::class, 'state'])->name('chat.state');
    Route::post('/model', [ChatAppController::class, 'selectModel'])->name('chat.model');
    Route::post('/conversation', [ChatAppController::class, 'selectConversation'])->name('chat.conversation.select');
    Route::post('/conversation/new', [ChatAppController::class, 'newConversation'])->name('chat.conversation.new');
    Route::post('/conversation/document', [ChatAppController::class, 'uploadConversationDocument'])->name('chat.conversation.document');
    Route::delete('/conversation', [ChatAppController::class, 'deleteConversation'])->name('chat.conversation.delete');
    Route::post('/rag', [ChatAppController::class, 'toggleRag'])->name('chat.rag');
    Route::post('/collection', [ChatAppController::class, 'selectCollection'])->name('chat.collection.select');
    Route::post('/collection/create', [ChatAppController::class, 'createCollection'])->name('chat.collection.create');
    Route::post('/collection/document', [ChatAppController::class, 'uploadCollectionDocument'])->name('chat.collection.document');
    Route::delete('/collection', [ChatAppController::class, 'deleteCollection'])->name('chat.collection.delete');
    Route::post('/prepare', [ChatAppController::class, 'prepareMessage'])->name('chat.prepare');
    Route::post('/openai-account', [ChatAppController::class, 'saveOpenAIAccount'])->name('chat.openai.save');
    Route::post('/openai-account/test', [ChatAppController::class, 'testOpenAIAccount'])->name('chat.openai.test');
    Route::delete('/openai-account', [ChatAppController::class, 'disconnectOpenAIAccount'])->name('chat.openai.disconnect');
    Route::post('/provider-accounts/{provider}', [ChatAppController::class, 'saveProviderAccount'])->name('chat.provider.save');
    Route::post('/provider-accounts/{provider}/test', [ChatAppController::class, 'testProviderAccount'])->name('chat.provider.test');
    Route::delete('/provider-accounts/{provider}', [ChatAppController::class, 'disconnectProviderAccount'])->name('chat.provider.disconnect');
});

// Redirection des anciennes routes vers la page d'accueil
Route::redirect('dashboard', '/')->name('dashboard');
Route::redirect('chat', '/');

// Ne pas définir de routes personnalisées pour Filament ici
// Filament gère ses propres routes via son système interne

require __DIR__.'/auth.php';
