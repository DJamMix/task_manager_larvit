<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BoardColumn extends Model
{
    protected $fillable = [
        'board_id',
        'status_id',
        'name',
        'sort_order',
        'wip_limit',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'wip_limit' => 'integer',
    ];

    public function board(): BelongsTo
    {
        return $this->belongsTo(Board::class);
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(WorkflowStatus::class, 'status_id');
    }

    public function displayName(): string
    {
        return $this->name ?: ($this->status?->name ?? 'Колонка');
    }
}
