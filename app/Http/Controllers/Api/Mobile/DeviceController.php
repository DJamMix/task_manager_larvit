<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DeviceController extends Controller
{
    public function storePushToken(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:512'],
            'platform' => ['nullable', 'string', 'max:32'],
        ]);

        if (!Schema::hasTable('mobile_push_tokens')) {
            return response()->json([
                'ok' => false,
                'message' => 'Таблица mobile_push_tokens ещё не создана. Выполните migrate.',
            ], 503);
        }

        $userId = (int) $request->user()->id;
        DB::table('mobile_push_tokens')->updateOrInsert(
            ['token' => $data['token']],
            [
                'user_id' => $userId,
                'platform' => $data['platform'] ?? 'android',
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        return response()->json(['ok' => true]);
    }
}
