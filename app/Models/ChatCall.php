<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatCall extends Model
{
    public const STATUS_RINGING = 'ringing';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_ENDED = 'ended';

    protected $fillable = [
        'chat_id',
        'started_by',
        'room_name',
        'status',
        'video_enabled',
        'e2ee_key',
        'guest_token',
        'started_at',
        'ended_at',
    ];

    protected $casts = [
        'video_enabled' => 'bool',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function chat(): BelongsTo
    {
        return $this->belongsTo(Chat::class);
    }

    public function starter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(ChatCallParticipant::class);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [self::STATUS_RINGING, self::STATUS_ACTIVE], true);
    }
}
