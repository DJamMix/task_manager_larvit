<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Orchid\Screen\AsSource;

class Chat extends Model
{
    use AsSource;

    protected $fillable = [
        'title',
        'type',
        'created_by',
        'description',
        'avatar_path',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'chat_user')
            ->withPivot(['role', 'last_read_at', 'is_muted', 'is_pinned', 'pinned_at'])
            ->withTimestamps();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class);
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(ChatMessage::class)->latestOfMany();
    }

    public function isMember(?int $userId = null): bool
    {
        $userId = $userId ?? auth()->id();

        return $userId && $this->members()->where('users.id', $userId)->exists();
    }

    public function isOwner(?int $userId = null): bool
    {
        $userId = $userId ?? auth()->id();

        return $userId && $this->members()
            ->where('users.id', $userId)
            ->wherePivot('role', 'owner')
            ->exists();
    }

    public function displayTitle(?int $viewerId = null): string
    {
        if ($this->type === 'direct') {
            $viewerId = $viewerId ?? auth()->id();
            $other = $this->members->first(fn (User $u) => (int) $u->id !== (int) $viewerId);

            return $other?->displayName() ?? ($this->title ?: 'Личный чат');
        }

        return $this->title ?: 'Групповой чат';
    }

    public function avatarUrl(?int $viewerId = null): string
    {
        if ($this->type === 'direct') {
            return $this->otherMember($viewerId)?->avatarUrl() ?? '';
        }

        if (!empty($this->avatar_path)) {
            return User::resolveStoredAvatarPath((string) $this->avatar_path);
        }

        return '';
    }

    public function avatarInitials(?int $viewerId = null): string
    {
        if ($this->type === 'direct') {
            return $this->otherMember($viewerId)?->avatarInitials() ?? '?';
        }

        $title = trim($this->displayTitle($viewerId));

        return $title !== '' ? mb_strtoupper(mb_substr($title, 0, 1)) : '#';
    }

    public function avatarColor(?int $viewerId = null): string
    {
        if ($this->type === 'direct') {
            return $this->otherMember($viewerId)?->avatarColor() ?? '#64748b';
        }

        $palette = [
            '#3b82f6', '#6366f1', '#8b5cf6', '#a855f7',
            '#ec4899', '#f43f5e', '#ef4444', '#f97316',
            '#eab308', '#22c55e', '#14b8a6', '#06b6d4',
        ];

        return $palette[(int) $this->id % count($palette)];
    }

    public function otherMember(?int $viewerId = null): ?User
    {
        if ($this->type !== 'direct') {
            return null;
        }

        $viewerId = $viewerId ?? auth()->id();

        // Стабильный выбор: минимальный id среди «не я» (не зависит от порядка eager load)
        return $this->members
            ->filter(fn (User $u) => (int) $u->id !== (int) $viewerId)
            ->sortBy('id')
            ->first();
    }

    /**
     * Кто уже видел сообщение (по last_read_at участников).
     *
     * @return list<array{id: int, name: string, initials: string, color: string, read_at: string|null}>
     */
    public function readersForMessage(ChatMessage $message): array
    {
        $this->loadMissing('members');

        return $this->members
            ->reject(fn (User $u) => (int) $u->id === (int) $message->user_id)
            ->filter(function (User $u) use ($message) {
                $readAt = $u->pivot?->last_read_at;
                if (!$readAt) {
                    return false;
                }
                $readAt = \Illuminate\Support\Carbon::parse($readAt);

                return $readAt->greaterThanOrEqualTo($message->created_at);
            })
            ->map(fn (User $u) => [
                'id' => (int) $u->id,
                'name' => $u->displayName(),
                'initials' => $u->avatarInitials(),
                'color' => $u->avatarColor(),
                'read_at' => $u->pivot?->last_read_at
                    ? \Illuminate\Support\Carbon::parse($u->pivot->last_read_at)->format('d.m H:i')
                    : null,
            ])
            ->values()
            ->all();
    }

    /** sent | partial | read */
    public function readStatusForMessage(ChatMessage $message): string
    {
        $this->loadMissing('members');
        $othersCount = $this->members
            ->reject(fn (User $u) => (int) $u->id === (int) $message->user_id)
            ->count();

        if ($othersCount === 0) {
            return 'sent';
        }

        $readCount = count($this->readersForMessage($message));

        if ($readCount === 0) {
            return 'sent';
        }

        if ($readCount >= $othersCount) {
            return 'read';
        }

        return 'partial';
    }

    public function unreadCountFor(int $userId): int
    {
        $pivot = $this->members()->where('users.id', $userId)->first()?->pivot;
        $lastRead = $pivot?->last_read_at;

        $q = $this->messages()->where('user_id', '!=', $userId);
        if ($lastRead) {
            $q->where('created_at', '>', $lastRead);
        }

        return $q->count();
    }
}