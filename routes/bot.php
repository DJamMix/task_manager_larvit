<?php

use App\Http\Controllers\Api\BotApiController;
use App\Http\Middleware\AuthenticateBotToken;
use Illuminate\Support\Facades\Route;

/*
| Telegram-style Bot API:
|   POST/GET /api/bot{token}/{method}
|   Authorization: Bearer {token} + /api/bot/{method}
*/

Route::match(['get', 'post'], '/bot{token}/{method}', [BotApiController::class, 'dispatch'])
    ->where('token', '[0-9]+:[A-Za-z0-9_-]+')
    ->where('method', '[A-Za-z]+')
    ->middleware(AuthenticateBotToken::class);

Route::match(['get', 'post'], '/bot/{method}', [BotApiController::class, 'dispatch'])
    ->where('method', '[A-Za-z]+')
    ->middleware(AuthenticateBotToken::class);
