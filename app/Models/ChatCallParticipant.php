<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatCallParticipant extends Model
{
    public const STATUS_INVITED = 'invited';
    public const STATUS_JOINED = 'joined';
    public const STATUS_LEFT = 'left';
    public const STATUS_DECLINED = 'declined';

    protected $fillable = [
        'chat_call_id',
        'user_id',
        'status',
        'joined_at',
        'left_at',
    ];

    protected $casts = [
        'joined_at' => 'datetime',
        'left_at' => 'datetime',
    ];

    public function call(): BelongsTo
    {
        return $this->belongsTo(ChatCall::class, 'chat_call_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
