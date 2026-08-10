<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Bot;
use App\Services\BotService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateBotToken
{
    public function __construct(private readonly BotService $bots) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = (string) ($request->route('token') ?? '');
        if ($token === '') {
            $header = (string) $request->header('Authorization', '');
            if (str_starts_with($header, 'Bearer ')) {
                $token = trim(substr($header, 7));
            } elseif (str_starts_with($header, 'Bot ')) {
                $token = trim(substr($header, 4));
            }
        }

        $bot = $this->bots->findByToken($token);
        if (! $bot instanceof Bot || ! $bot->is_active) {
            return response()->json([
                'ok' => false,
                'error_code' => 401,
                'description' => 'Unauthorized: invalid bot token',
            ], 401);
        }

        $request->attributes->set('bot', $bot);

        return $next($request);
    }
}
