<?php

namespace App\Models;

use App\Services\MessageHtmlRenderer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Orchid\Attachment\Attachable;
use Orchid\Screen\AsSource;

class ChatMessage extends Model
{
    use AsSource, Attachable, SoftDeletes;

    protected $fillable = [
        'chat_id',
        'user_id',
        'parent_id',
        'text',
        'plain_text',
        'mentioned_user_ids',
        'task_id',
        'is_system',
        'forwarded_from_message_id',
        'forwarded_from_chat_id',
        'forwarded_from_user_id',
        'deleted_by',
    ];

    protected $casts = [
        'text' => 'array',
        'mentioned_user_ids' => 'array',
        'is_system' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function chat(): BelongsTo
    {
        return $this->belongsTo(Chat::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function deletedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function hides(): HasMany
    {
        return $this->hasMany(ChatMessageHide::class, 'chat_message_id');
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function forwardedFromMessage(): BelongsTo
    {
        return $this->belongsTo(self::class, 'forwarded_from_message_id');
    }

    public function forwardedFromChat(): BelongsTo
    {
        return $this->belongsTo(Chat::class, 'forwarded_from_chat_id');
    }

    public function forwardedFromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'forwarded_from_user_id');
    }

    /**
     * Автор оригинала для пересланных сообщений (как «Forwarded from» в Telegram).
     */
    public function forwardOriginUser(): ?User
    {
        if (!$this->forwarded_from_message_id && !$this->forwarded_from_user_id) {
            return null;
        }

        if ($this->relationLoaded('forwardedFromUser') && $this->forwardedFromUser) {
            return $this->forwardedFromUser;
        }

        if ($this->forwarded_from_user_id) {
            return $this->forwardedFromUser()->first();
        }

        $origin = $this->relationLoaded('forwardedFromMessage')
            ? $this->forwardedFromMessage
            : $this->forwardedFromMessage()->with('user')->first();

        return $origin?->forwardOriginUser() ?? $origin?->user;
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->whereDoesntHave(
            'hides',
            fn (Builder $q) => $q->where('user_id', $user->id)
        );
    }

    public function getFormattedTextAttribute(): string
    {
        if ($this->trashed()) {
            return '<em class="bx-msg__deleted">Сообщение удалено</em>';
        }

        $labels = [];
        if (!empty($this->mentioned_user_ids)) {
            $labels = User::query()
                ->whereIn('id', $this->mentioned_user_ids)
                ->get()
                ->flatMap(fn (User $u) => array_filter([$u->displayName(), $u->name]))
                ->unique()
                ->values()
                ->all();
        }

        return app(MessageHtmlRenderer::class)->render($this->text, $this->plain_text, $labels);
    }
}
