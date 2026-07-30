<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Mobile\MobileChatPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request, MobileChatPresenter $presenter): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        /** @var User|null $user */
        $user = User::query()->where('email', $data['email'])->first();

        if (!$user || !Hash::check($data['password'], (string) $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Неверный email или пароль'],
            ]);
        }

        if (!$user->hasAccess('platform.systems.chats')
            && !$user->hasAccess('platform.systems.my_tasks')
            && !$user->hasAccess('platform.systems.contact.tasks')
            && !$user->hasAccess('platform.systems.client.project.tasks.view')) {
            throw ValidationException::withMessages([
                'email' => ['Нет доступа к мобильному приложению'],
            ]);
        }

        $device = $data['device_name'] ?? ('mobile-' . substr((string) $request->userAgent(), 0, 40));
        $token = $user->createToken($device)->plainTextToken;

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $presenter->userBrief($user->load('roles')),
        ]);
    }

    public function me(Request $request, MobileChatPresenter $presenter): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->load('roles');

        return response()->json([
            'user' => $presenter->userBrief($user),
            'capabilities' => [
                'chats' => $user->hasAccess('platform.systems.chats'),
                'my_tasks' => $user->hasAccess('platform.systems.my_tasks')
                    || $user->hasAccess('platform.systems.contact.tasks')
                    || $user->hasAccess('platform.systems.client.project.tasks.view'),
                'calls' => $user->hasAccess('platform.systems.chats'),
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json(['ok' => true]);
    }
}
