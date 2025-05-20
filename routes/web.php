<?php

use App\Http\Controllers\ChatController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');
Route::middleware('auth')->group(function () {
    Route::get('/chat', [ChatController::class, 'index'])->name('chat.index');
    Route::get('/chat/search', [ChatController::class, 'searchUsers'])->name('chat.search');
    Route::get('/chat/conversation/{id}', [ChatController::class, 'getConversation'])->name('chat.conversation');
    Route::post('/chat/send', [ChatController::class, 'sendMessage'])->name('chat.send');
});
