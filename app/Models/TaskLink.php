<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Orchid\Screen\AsSource;

class TaskLink extends Model
{
    use AsSource;

    public const RELATES = 'relates';
    public const BLOCKS = 'blocks';
    public const BLOCKED_BY = 'blocked_by';
    public const PARENT = 'parent';
    public const SUBTASK = 'subtask';

    protected $fillable = [
        'task_id',
        'related_task_id',
        'relation',
        'created_by',
    ];

    public static function relationLabels(): array
    {
        return [
            self::RELATES => 'Связана с',
            self::BLOCKS => 'Блокирует',
            self::BLOCKED_BY => 'Блокируется',
            self::PARENT => 'Родительская',
            self::SUBTASK => 'Подзадача',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'task_id');
    }

    public function relatedTask(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'related_task_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function label(): string
    {
        return self::relationLabels()[$this->relation] ?? $this->relation;
    }
}
