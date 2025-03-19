<?php

namespace App\Models;

use App\CoreLayer\Enums\TaskStatusEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Task extends Model
{
    protected $fillable = [
        'creator_id',
        'name',
        'observers_ids',
        'executor_id',
        'description',
        'start_datetime',
        'end_datetime',
        'cost_estimation',
        'project_id',
        'status',
        'pay_status',
        'hours_spent',
        'task_category_id',
    ];

    /**
     * Связь с пользователем, который создал задачу.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    /**
     * Связь с пользователем, который является исполнителем задачи.
     */
    public function executor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'executor_id');
    }

    /**
     * Связь с проектом, к которому принадлежит задача.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    /**
     * Связь с категорией задачи.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(TaskCategory::class, 'task_category_id');
    }
}
