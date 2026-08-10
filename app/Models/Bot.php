<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Orchid\Screen\AsSource;

class Bot extends Model
{
    use AsSource;

    protected $fillable = [
        'user_id',
        'name',
        'username',
        'description',
        'token_hash',
        'token_hint',
        'is_active',
        'can_join_groups',
        'can_read_messages',
        'webhook_url',
        'webhook_secret',
        'webhook_error_count',
        'webhook_last_error_at',
        'webhook_last_error',
        'commands',
        'settings',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'can_join_groups' => 'boolean',
        'can_read_messages' => 'boolean',
        'commands' => 'array',
        'settings' => 'array',
        'webhook_last_error_at' => 'datetime',
    ];

    protected $hidden = [
        'token_hash',
        'webhook_secret',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updates(): HasMany
    {
        return $this->hasMany(BotUpdate::class);
    }

    public function displayUsername(): string
    {
        return '@'.$this->username;
    }

    public function apiBaseUrl(): string
    {
        return url('/api/bot');
    }
}
