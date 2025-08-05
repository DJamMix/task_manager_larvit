<?php

namespace App\Models;

use App\CoreLayer\Enums\TaskStatusEnum;
use App\Orchid\Filters\TaskCategoryFilter;
use App\Orchid\Filters\TaskStatusFilter;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Orchid\Attachment\Attachable;
use Orchid\Filters\Filterable;
use Orchid\Screen\AsSource;

class Task extends Model
{
    use HasFactory, AsSource, Filterable, Attachable;

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
        'estimation_hours',
        'type_task',
        'priority',
    ];

    protected $allowedFilters = [
        'task_category_id' => TaskCategoryFilter::class,
        'status' => TaskStatusFilter::class,
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

    public function comments()
    {
        return $this->hasMany(Comment::class);
    }

    /**
     * Связь с записями учета времени
     */
    public function timeEntries(): HasMany
    {
        return $this->hasMany(TrackingTime::class);
    }

    /**
     * Связь с прикрепленными файлами
     */
    public function attachments()
    {
        return $this->hasMany(TaskAttachment::class);
    }
}
