<?php

namespace App\Models;

use App\CoreLayer\Enums\TaskStatusEnum;
use App\CoreLayer\Enums\TaskPriorityEnum;
use App\Orchid\Filters\TaskCategoryFilter;
use App\Orchid\Filters\TaskCreatedAtFilter;
use App\Orchid\Filters\TaskExecutorFilter;
use App\Orchid\Filters\TaskPriorityFilter;
use App\Orchid\Filters\TaskProjectFilter;
use App\Orchid\Filters\TaskSearchFilter;
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
        'queue_id',
        'queue_number',
        'status',
        'status_id',
        'pay_status',
        'hours_spent',
        'task_category_id',
        'estimation_hours',
        'type_task',
        'priority',
        'sprint_id',
        'board_id',
        'board_sort',
    ];

    protected $casts = [
        'start_datetime' => 'datetime',
        'end_datetime' => 'datetime',
        'pay_status' => 'boolean',
        'hours_spent' => 'float',
        'estimation_hours' => 'float',
        'observers_ids' => 'array',
    ];

    protected $allowedFilters = [
        'task_category_id' => TaskCategoryFilter::class,
        'status' => TaskStatusFilter::class,
        'project' => TaskProjectFilter::class,
        'executor' => TaskExecutorFilter::class,
        'priority' => TaskPriorityFilter::class,
        'search' => TaskSearchFilter::class,
        'created_at' => TaskCreatedAtFilter::class,
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

    public function workflowStatus(): BelongsTo
    {
        return $this->belongsTo(WorkflowStatus::class, 'status_id');
    }

    public function sprint(): BelongsTo
    {
        return $this->belongsTo(Sprint::class);
    }

    public function board(): BelongsTo
    {
        return $this->belongsTo(Board::class);
    }

    public function statusLabel(): string
    {
        if ($this->workflowStatus) {
            return $this->workflowStatus->name;
        }

        return TaskStatusEnum::tryFrom((string) $this->status)?->label() ?? (string) $this->status;
    }

    public function statusColor(): string
    {
        if ($this->workflowStatus) {
            return $this->workflowStatus->color;
        }

        return TaskStatusEnum::tryFrom((string) $this->status)?->color() ?? '#64748b';
    }

    public function queue(): BelongsTo
    {
        return $this->belongsTo(TaskQueue::class, 'queue_id');
    }

    public function links(): HasMany
    {
        return $this->hasMany(TaskLink::class, 'task_id');
    }

    public function incomingLinks(): HasMany
    {
        return $this->hasMany(TaskLink::class, 'related_task_id');
    }

    /** Ключ задачи: PHP-42 или #123 если очередь не задана. */
    public function displayKey(): string
    {
        if ($this->queue_id && $this->queue_number) {
            $key = $this->relationLoaded('queue')
                ? $this->queue?->key
                : $this->queue()->value('key');

            if ($key) {
                return strtoupper((string) $key) . '-' . $this->queue_number;
            }
        }

        return '#' . $this->id;
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
    // public function attachments()
    // {
    //     return $this->hasMany(TaskAttachment::class);
    // }

    /**
    * Связь актов и задач
    */
    public function acts()
    {
        return $this->belongsToMany(Act::class, 'act_task')
            ->withPivot('hours', 'included_at')
            ->withTimestamps()
            ->orderByDesc('acts.date');
    }

    /**
    * Проверка наличия связанных актов
    */

    public function isInAct(): bool
    {
        return $this->acts()->exists();
    }

    /**
    * Аксессор для получения номеров актов строкой (например: "123, 456, 789")
    */

    public function getActNumbersAttribute(): string
    {
        return $this->acts->pluck('number')->implode(', ');
    }

    /**
     * Трекинг времени доступен исполнителю сразу после назначения,
     * независимо от оценки. Оценка живёт отдельно (estimation_hours).
     */
    public function canTrackTime(?int $userId = null): bool
    {
        $userId = $userId ?? auth()->id();

        if (!$userId || (int) $this->executor_id !== (int) $userId) {
            return false;
        }

        // Трекинг с любого статуса, кроме отменённой. Оценка живёт отдельно.
        return $this->status !== TaskStatusEnum::CANCELED->value;
    }

    public function priorityRowClass(): string
    {
        return TaskPriorityEnum::tryFrom((string) $this->priority)?->rowClass() ?? '';
    }

    public function isOverdue(): bool
    {
        if (!$this->end_datetime) {
            return false;
        }

        if (in_array($this->status, [
            TaskStatusEnum::COMPLETED->value,
            TaskStatusEnum::CANCELED->value,
            TaskStatusEnum::UNPAID->value,
        ], true)) {
            return false;
        }

        return now()->greaterThan($this->end_datetime);
    }

    public function spentVsEstimateRatio(): ?float
    {
        $estimate = (float) $this->estimation_hours;

        if ($estimate <= 0) {
            return null;
        }

        return (float) $this->hours_spent / $estimate;
    }

    /**
     * @return list<int>
     */
    public function observerIds(): array
    {
        return collect($this->observers_ids ?? [])
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    public function isObserver(?int $userId = null): bool
    {
        $userId = $userId ?? auth()->id();

        return $userId && in_array((int) $userId, $this->observerIds(), true);
    }

    public function canDiscuss(?int $userId = null): bool
    {
        $userId = $userId ?? auth()->id();
        if (!$userId) {
            return false;
        }

        if ((int) $this->executor_id === (int) $userId || (int) $this->creator_id === (int) $userId) {
            return true;
        }

        $user = User::find($userId);
        if ($user?->hasAccess('platform.systems.tasks')) {
            return true;
        }

        // Контакт клиента — только наблюдатель и только в своих проектах
        if ($user?->isClientContact()) {
            return $this->isObserver($userId)
                && $user->projects()->where('projects.id', $this->project_id)->exists();
        }

        if ($this->isObserver($userId)) {
            return true;
        }

        // Клиент / заказчик проекта — все задачи проекта
        if ($user?->isClientWithTaskAccess()) {
            return $user->projects()->where('projects.id', $this->project_id)->exists();
        }

        return false;
    }

    /** Может открыть карточку задачи (в т.ч. из чата) */
    public function canView(?int $userId = null): bool
    {
        return $this->canDiscuss($userId);
    }

    public function canManageTask(?int $userId = null): bool
    {
        $userId = $userId ?? auth()->id();
        $user = $userId ? User::find($userId) : null;

        return (bool) ($user?->hasAccess('platform.systems.tasks') || $user?->hasAccess('platform.systems.projects'));
    }

    public function canChangeWorkflow(?int $userId = null): bool
    {
        $userId = $userId ?? auth()->id();

        // Наблюдатель только пишет в обсуждении
        if ($this->isObserver($userId) && (int) $this->executor_id !== (int) $userId) {
            return false;
        }

        return (int) $this->executor_id === (int) $userId
            || $this->canManageTask($userId);
    }

    public function participantsForNotify(): array
    {
        $ids = collect([
            $this->executor_id,
            $this->creator_id,
            ...$this->observerIds(),
        ])->filter()->map(fn ($id) => (int) $id);

        foreach ($this->project?->clients ?? [] as $client) {
            $ids->push((int) $client->id);
        }

        $ids = $ids->unique()->values();

        return User::query()
            ->whereIn('id', $ids)
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (User $u) => [$u->id => $u->displayName()])
            ->all();
    }

    public function observers()
    {
        return User::query()->whereIn('id', $this->observerIds())->orderBy('name')->get();
    }
}
