<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Mobile\AttachmentController;
use App\Http\Controllers\Api\Mobile\AuthController;
use App\Http\Controllers\Api\Mobile\CallController;
use App\Http\Controllers\Api\Mobile\ChatController;
use App\Http\Controllers\Api\Mobile\TaskController;
use Illuminate\Support\Facades\Route;

Route::prefix('mobile')->group(function () {
    Route::post('login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);

        Route::get('chats', [ChatController::class, 'index']);
        Route::get('chats/poll', [ChatController::class, 'poll']);
        Route::get('chats/{chat}', [ChatController::class, 'show']);
        Route::get('chats/{chat}/messages', [ChatController::class, 'history']);
        Route::post('chats/{chat}/messages', [ChatController::class, 'send']);
        Route::post('chats/{chat}/messages/delete', [ChatController::class, 'delete']);
        Route::post('chats/{chat}/typing', [ChatController::class, 'typing']);

        Route::post('chats/{chat}/calls/start', [CallController::class, 'start']);
        Route::post('calls/{call}/join', [CallController::class, 'join']);
        Route::post('calls/{call}/leave', [CallController::class, 'leave']);
        Route::post('calls/{call}/end', [CallController::class, 'end']);

        Route::get('tasks', [TaskController::class, 'index']);
        Route::get('tasks/{task}', [TaskController::class, 'show']);
        Route::post('tasks/{task}/comments', [TaskController::class, 'comment']);

        Route::get('attachments/{attachment}', [AttachmentController::class, 'show']);
    });
});
