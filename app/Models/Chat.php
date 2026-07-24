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
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'chat_user')
            ->withPivot(['role', 'last_read_at', 'is_muted'])
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
