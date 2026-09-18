<?php

use App\Http\Controllers\ChatBackgroundController;
use App\Http\Controllers\ChatController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/chat')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('chat', [ChatController::class, 'show'])->name('chat');
    Route::post('chat', [ChatController::class, 'store'])->name('chat.store');
    Route::post('chat/stream', [ChatController::class, 'stream'])->name('chat.stream');

    // Same chat, but the agent runs in a queued job and streams through Redis.
    Route::get('chat-background', [ChatBackgroundController::class, 'show'])->name('chat-background');
    Route::post('chat-background', [ChatBackgroundController::class, 'store'])->name('chat-background.store');
    Route::post('chat-background/stream', [ChatBackgroundController::class, 'stream'])->name('chat-background.stream');
});

require __DIR__.'/settings.php';
