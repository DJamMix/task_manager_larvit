<?php

namespace App\Models;

use App\Services\MessageHtmlRenderer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Orchid\Attachment\Attachable;
use Orchid\Screen\AsSource;

class ChatMessage extends Model
{
    use AsSource, Attachable;

    protected $fillable = [
        'chat_id',
        'user_id',
        'parent_id',
        'text',
        'plain_text',
        'mentioned_user_ids',
        'task_id',
        'is_system',
    ];

    protected $casts = [
        'text' => 'array',
        'mentioned_user_ids' => 'array',
        'is_system' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function chat(): BelongsTo
    {
        return $this->belongsTo(Chat::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function getFormattedTextAttribute(): string
    {
        return app(MessageHtmlRenderer::class)->render($this->text, $this->plain_text);
    }
}
