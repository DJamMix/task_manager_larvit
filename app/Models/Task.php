<?php

namespace App\Models;

use App\CoreLayer\Enums\TaskStatusEnum;
use App\Orchid\Filters\TaskCategoryFilter;
use App\Orchid\Filters\TaskExecutorFilter;
use App\Orchid\Filters\TaskPriorityFilter;
use App\Orchid\Filters\TaskProjectFilter;
use App\Orchid\Filters\TaskStatusFilter;
use App\Orchid\Presenters\TaskPresenter;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Scout\Searchable;
use Orchid\Attachment\Attachable;
use Orchid\Filters\Filterable;
use Orchid\Screen\AsSource;

class Task extends Model
{
    use HasFactory, AsSource, Filterable, Attachable, Searchable;

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
        'project' => TaskProjectFilter::class,
        'executor' => TaskExecutorFilter::class,
        'priority' => TaskPriorityFilter::class,
        'search' => TaskSearchFilter::class,
    ];

    /**
     * Get the presenter for the model.
     *
     * @return TaskPresenter
     */
    public function presenter()
    {
        return new TaskPresenter($this);
    }

    /**
     * Get the indexable data array for the model.
     *
     * @return array
     */
    public function toSearchableArray()
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => strip_tags($this->description),
            'status' => $this->status,
            'priority' => $this->priority,
            'type_task' => $this->type_task, // Исправлено с type на type_task
            'project_id' => $this->project_id,
            'executor_id' => $this->executor_id,
            'creator_id' => $this->creator_id,
            'created_at' => $this->created_at->timestamp,
            // Убираем searchable поле - Meilisearch сам формирует поисковый индекс
        ];
    }

    /**
     * Get the index name for the model.
     *
     * @return string
     */
    public function searchableAs()
    {
        return 'tasks';
    }

    /**
     * Modify the query used to retrieve models when making all of the models searchable.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function makeAllSearchableUsing($query)
    {
        return $query->with(['project', 'executor', 'creator', 'category']);
    }

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

    // /**
    //  * Связь с прикрепленными файлами
    //  */
    // public function attachments()
    // {
    //     return $this->hasMany(TaskAttachment::class);
    // }
}
