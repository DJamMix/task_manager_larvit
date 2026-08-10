<?php

declare(strict_types=1);

namespace App\Services\Mobile;

use App\Models\Chat;
use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Support\Str;

final class MobileChatPresenter
{
    public function chatSummary(Chat $chat, User $viewer): array
    {
        $latest = $chat->latestMessage;

        return [
            'id' => (int) $chat->id,
            'type' => (string) $chat->type,
            'title' => $chat->displayTitle($viewer->id),
            'unread' => (int) ($chat->unread_count ?? 0),
            'muted' => (bool) ($chat->is_muted ?? false),
            'pinned' => (bool) ($chat->is_pinned ?? false),
            'preview' => $latest
                ? Str::limit(trim(($latest->user?->name ?: '') . ': ' . (string) $latest->plain_text), 120)
                : '',
            'last_id' => $latest ? (int) $latest->id : 0,
            'updated_at' => $latest?->created_at?->toIso8601String()
                ?? $chat->updated_at?->toIso8601String(),
            'avatar' => [
                'url' => $chat->avatarUrl($viewer->id),
                'initials' => $chat->avatarInitials($viewer->id),
                'color' => $chat->avatarColor($viewer->id),
                'shape' => $chat->type === 'direct' ? 'round' : 'square',
            ],
        ];
    }

    public function message(ChatMessage $message, User $viewer, ?string $token = null): array
    {
        $mine = (int) $message->user_id === (int) $viewer->id;
        $attachments = [];

        foreach ($message->attachment ?? [] as $file) {
            $mime = strtolower((string) ($file->mime ?? ''));
            $group = strtolower((string) ($file->group ?? ''));
            $ext = strtolower((string) ($file->extension ?? pathinfo((string) $file->original_name, PATHINFO_EXTENSION)));
            $isVoice = $group === 'voice'
                || str_starts_with($mime, 'audio/')
                || in_array($ext, ['webm', 'ogg', 'oga', 'mp3', 'm4a', 'wav', 'aac', 'opus'], true);
            $isImage = str_starts_with($mime, 'image/');

            $url = url('/api/mobile/attachments/' . $file->id . '?inline=1');
            if ($token) {
                $url .= '&token=' . urlencode($token);
            }

            $attachments[] = [
                'id' => (int) $file->id,
                'name' => (string) $file->original_name,
                'mime' => $mime,
                'kind' => $isVoice ? 'voice' : ($isImage ? 'image' : 'file'),
                'url' => $url,
            ];
        }

        $parent = null;
        if ($message->parent) {
            $parent = [
                'id' => (int) $message->parent->id,
                'author' => $message->parent->user?->name ?? 'Участник',
                'preview' => Str::limit((string) $message->parent->plain_text, 80),
            ];
        }

        $task = null;
        if ($message->task) {
            $task = [
                'id' => (int) $message->task->id,
                'name' => (string) $message->task->name,
            ];
        }

        return [
            'id' => (int) $message->id,
            'chat_id' => (int) $message->chat_id,
            'mine' => $mine,
            'system' => (bool) $message->is_system,
            'deleted' => $message->trashed(),
            'text' => (string) ($message->plain_text ?? ''),
            'html' => $message->trashed()
                ? '<em>Сообщение удалено</em>'
                : (string) $message->formatted_text,
            'author' => [
                'id' => (int) ($message->user_id ?? 0),
                'name' => $message->user?->name ?? 'Система',
                'initials' => $message->user?->avatarInitials() ?? '•',
                'color' => $message->user?->avatarColor() ?? '#64748b',
                'avatar_url' => $message->user?->avatarUrl(),
            ],
            'parent' => $parent,
            'task' => $task,
            'forwarded' => (bool) ($message->forwarded_from_message_id || $message->forwarded_from_user_id),
            'forwarded_from' => (function () use ($message) {
                $origin = $message->forwardOriginUser();
                if (!$origin) {
                    return null;
                }

                return [
                    'id' => (int) $origin->id,
                    'name' => $origin->displayName(),
                    'initials' => $origin->avatarInitials(),
                    'color' => $origin->avatarColor(),
                    'avatar_url' => $origin->avatarUrl(),
                ];
            })(),
            'attachments' => $attachments,
            'created_at' => $message->created_at?->toIso8601String(),
            'created_label' => $message->created_at?->format('H:i') ?? '',
        ];
    }

    public function userBrief(User $user): array
    {
        return [
            'id' => (int) $user->id,
            'name' => (string) $user->name,
            'display_name' => $user->displayName(),
            'email' => (string) $user->email,
            'avatar_url' => $user->avatarUrl(),
            'initials' => $user->avatarInitials(),
            'color' => $user->avatarColor(),
            'roles' => $user->roles->pluck('slug')->values()->all(),
        ];
    }
}
