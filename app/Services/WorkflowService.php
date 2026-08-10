<?php

namespace App\Services;

use App\CoreLayer\Enums\TaskStatusEnum;
use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkflowStatus;
use App\Models\WorkflowTransition;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WorkflowService
{
    /**
     * Сид статусов и переходов из текущего enum + дефолтная доска.
     * Быстрый путь: после первой инициализации почти ничего не делает (cache).
     */
    public function bootstrapDefaults(?User $actor = null): void
    {
        if (Cache::get('workflow.defaults_ready')) {
            return;
        }

        $expectedStatuses = count(TaskStatusEnum::cases());
        $ready = WorkflowStatus::query()->count() >= $expectedStatuses
            && WorkflowTransition::query()->exists()
            && Board::query()->exists();

        if ($ready) {
            $this->backfillMissingTaskStatusIds();
            Cache::forever('workflow.defaults_ready', 1);

            return;
        }

        DB::transaction(function () use ($actor) {
            $order = 0;
            $map = [];

            foreach (TaskStatusEnum::cases() as $case) {
                $category = match ($case) {
                    TaskStatusEnum::COMPLETED, TaskStatusEnum::CANCELED => 'done',
                    TaskStatusEnum::DRAFT, TaskStatusEnum::APPROVED, TaskStatusEnum::NEW => 'todo',
                    default => 'in_progress',
                };

                $status = WorkflowStatus::query()->updateOrCreate(
                    ['slug' => $case->value],
                    [
                        'name' => $case->label(),
                        'color' => $case->color(),
                        'category' => $category,
                        'sort_order' => $order++,
                        'is_initial' => $case === TaskStatusEnum::DRAFT,
                        'is_final' => in_array($case, [TaskStatusEnum::COMPLETED, TaskStatusEnum::CANCELED], true),
                        'is_active' => true,
                    ]
                );
                $map[$case->value] = $status->id;
            }

            $edges = [
                [TaskStatusEnum::DRAFT, TaskStatusEnum::APPROVED],
                [TaskStatusEnum::DRAFT, TaskStatusEnum::CANCELED],
                [TaskStatusEnum::APPROVED, TaskStatusEnum::ESTIMATION],
                [TaskStatusEnum::APPROVED, TaskStatusEnum::NEW],
                [TaskStatusEnum::ESTIMATION, TaskStatusEnum::ESTIMATION_REVIEW],
                [TaskStatusEnum::ESTIMATION_REVIEW, TaskStatusEnum::ESTIMATION],
                [TaskStatusEnum::ESTIMATION_REVIEW, TaskStatusEnum::NEW],
                [TaskStatusEnum::NEW, TaskStatusEnum::IN_PROGRESS],
                [TaskStatusEnum::IN_PROGRESS, TaskStatusEnum::TESTING_STAGE],
                [TaskStatusEnum::IN_PROGRESS, TaskStatusEnum::NEW],
                [TaskStatusEnum::TESTING_STAGE, TaskStatusEnum::IN_PROGRESS],
                [TaskStatusEnum::TESTING_STAGE, TaskStatusEnum::TESTING_PROD],
                [TaskStatusEnum::TESTING_PROD, TaskStatusEnum::TESTING_STAGE],
                [TaskStatusEnum::TESTING_PROD, TaskStatusEnum::DEMO],
                [TaskStatusEnum::DEMO, TaskStatusEnum::TESTING_PROD],
                [TaskStatusEnum::DEMO, TaskStatusEnum::UNPAID],
                [TaskStatusEnum::DEMO, TaskStatusEnum::COMPLETED],
                [TaskStatusEnum::UNPAID, TaskStatusEnum::COMPLETED],
                [TaskStatusEnum::UNPAID, TaskStatusEnum::DEMO],
                [TaskStatusEnum::COMPLETED, TaskStatusEnum::IN_PROGRESS],
            ];

            foreach ($edges as $i => [$from, $to]) {
                WorkflowTransition::query()->updateOrCreate(
                    [
                        'from_status_id' => $map[$from->value],
                        'to_status_id' => $map[$to->value],
                    ],
                    [
                        'name' => '→ '.$to->label(),
                        'sort_order' => $i,
                    ]
                );
            }

            // Привязка существующих задач
            Task::query()->whereNull('status_id')->orderBy('id')->chunkById(200, function ($tasks) use ($map) {
                foreach ($tasks as $task) {
                    $slug = (string) $task->status;
                    if (isset($map[$slug])) {
                        $task->forceFill(['status_id' => $map[$slug]])->saveQuietly();
                    }
                }
            });

            if (! Board::query()->exists()) {
                $board = Board::query()->create([
                    'name' => 'Основная доска',
                    'type' => 'kanban',
                    'description' => 'Kanban-доска по статусам workflow',
                    'is_default' => true,
                    'created_by' => $actor?->id,
                    'project_id' => Project::query()->value('id'),
                ]);

                $columnSlugs = [
                    TaskStatusEnum::NEW->value,
                    TaskStatusEnum::IN_PROGRESS->value,
                    TaskStatusEnum::TESTING_STAGE->value,
                    TaskStatusEnum::TESTING_PROD->value,
                    TaskStatusEnum::DEMO->value,
                    TaskStatusEnum::COMPLETED->value,
                ];

                foreach ($columnSlugs as $i => $slug) {
                    if (! isset($map[$slug])) {
                        continue;
                    }
                    BoardColumn::query()->create([
                        'board_id' => $board->id,
                        'status_id' => $map[$slug],
                        'sort_order' => $i,
                    ]);
                }
            }
        });

        $this->backfillMissingTaskStatusIds();
        Cache::forever('workflow.defaults_ready', 1);
    }

    /**
     * Разово (не чаще раза в 10 мин) проставляет status_id задачам без него.
     */
    private function backfillMissingTaskStatusIds(): void
    {
        if (! Cache::add('workflow.backfill_lock', 1, 600)) {
            return;
        }

        $map = WorkflowStatus::query()->pluck('id', 'slug');
        if ($map->isEmpty()) {
            return;
        }

        Task::query()
            ->whereNull('status_id')
            ->orderBy('id')
            ->limit(300)
            ->get(['id', 'status'])
            ->each(function (Task $task) use ($map) {
                $slug = (string) $task->status;
                if (isset($map[$slug])) {
                    $task->forceFill(['status_id' => $map[$slug]])->saveQuietly();
                }
            });
    }

    public function allowedTransitions(Task $task, User $actor): array
    {
        $fromId = $task->status_id
            ?: WorkflowStatus::query()->where('slug', $task->status)->value('id');

        if (! $fromId) {
            return [];
        }

        $isAdmin = $actor->hasAccess('platform.systems.tasks');
        $canWorkflow = $task->canChangeWorkflow((int) $actor->id);

        if (! $canWorkflow && ! $isAdmin) {
            return [];
        }

        // Админ может переводить в любой активный статус
        if ($isAdmin && request()->boolean('all_statuses')) {
            return WorkflowStatus::query()
                ->where('is_active', true)
                ->where('id', '!=', $fromId)
                ->orderBy('sort_order')
                ->get()
                ->map(fn (WorkflowStatus $s) => [
                    'id' => $s->id,
                    'slug' => $s->slug,
                    'name' => $s->name,
                    'color' => $s->color,
                ])
                ->all();
        }

        return Cache::remember("workflow.transitions.from.{$fromId}", 300, function () use ($fromId) {
            return WorkflowTransition::query()
                ->with('toStatus')
                ->where('from_status_id', $fromId)
                ->orderBy('sort_order')
                ->get()
                ->filter(fn (WorkflowTransition $t) => $t->toStatus && $t->toStatus->is_active)
                ->map(fn (WorkflowTransition $t) => [
                    'id' => $t->toStatus->id,
                    'slug' => $t->toStatus->slug,
                    'name' => $t->label(),
                    'color' => $t->toStatus->color,
                    'transition_id' => $t->id,
                ])
                ->values()
                ->all();
        });
    }

    public function changeStatus(Task $task, User $actor, int|string $toStatus, bool $force = false): Task
    {
        if (! $task->canChangeWorkflow((int) $actor->id) && ! $actor->hasAccess('platform.systems.tasks')) {
            abort(403);
        }

        $to = is_numeric($toStatus)
            ? WorkflowStatus::query()->findOrFail((int) $toStatus)
            : WorkflowStatus::query()->where('slug', (string) $toStatus)->firstOrFail();

        $fromId = $task->status_id
            ?: WorkflowStatus::query()->where('slug', $task->status)->value('id');

        $allowed = $force && $actor->hasAccess('platform.systems.tasks');
        if (! $allowed && $fromId) {
            $allowed = WorkflowTransition::query()
                ->where('from_status_id', $fromId)
                ->where('to_status_id', $to->id)
                ->exists();
        }

        // Админ всегда может
        if (! $allowed && $actor->hasAccess('platform.systems.tasks')) {
            $allowed = true;
        }

        if (! $allowed) {
            throw ValidationException::withMessages([
                'status' => 'Переход в статус «'.$to->name.'» не разрешён схемой workflow.',
            ]);
        }

        $fromName = $task->workflowStatus?->name
            ?: (TaskStatusEnum::tryFrom((string) $task->status)?->label() ?? $task->status);

        $fromSlug = (string) $task->status;

        $task->fill([
            'status' => $to->slug,
            'status_id' => $to->id,
        ])->save();

        try {
            app(TaskLogger::class)->logStatusChange($task, $actor, $to->slug, null, $fromSlug);
        } catch (\Throwable) {
        }

        return $task->fresh(['workflowStatus', 'executor', 'project', 'queue']);
    }

    public function graphPayload(): array
    {
        $statuses = WorkflowStatus::query()->where('is_active', true)->orderBy('sort_order')->get();
        $transitions = WorkflowTransition::query()->with(['fromStatus', 'toStatus'])->get();

        return [
            'statuses' => $statuses->map(fn (WorkflowStatus $s) => [
                'id' => $s->id,
                'slug' => $s->slug,
                'name' => $s->name,
                'color' => $s->color,
                'category' => $s->category,
                'is_initial' => $s->is_initial,
                'is_final' => $s->is_final,
                'sort_order' => $s->sort_order,
            ])->values()->all(),
            'transitions' => $transitions->map(fn (WorkflowTransition $t) => [
                'id' => $t->id,
                'from' => $t->from_status_id,
                'to' => $t->to_status_id,
                'name' => $t->name,
            ])->values()->all(),
        ];
    }

    public function saveGraph(array $statuses, array $transitions): void
    {
        DB::transaction(function () use ($statuses, $transitions) {
            $keepIds = [];
            $idMap = []; // client id (string|int) → real id

            foreach ($statuses as $i => $row) {
                $clientId = $row['id'] ?? null;
                $payload = [
                    'slug' => $row['slug'] ?? ('s_'.uniqid()),
                    'name' => $row['name'] ?? 'Статус',
                    'color' => $row['color'] ?? '#64748b',
                    'category' => $row['category'] ?? 'in_progress',
                    'sort_order' => (int) ($row['sort_order'] ?? $i),
                    'is_initial' => (bool) ($row['is_initial'] ?? false),
                    'is_final' => (bool) ($row['is_final'] ?? false),
                    'is_active' => (bool) ($row['is_active'] ?? true),
                ];

                $status = null;
                if ($clientId !== null && is_numeric($clientId)) {
                    $status = WorkflowStatus::query()->find((int) $clientId);
                }

                if ($status) {
                    // slug для встроенных статусов не ломаем без нужды
                    if ($status->slug && str_starts_with((string) $payload['slug'], 's_')) {
                        unset($payload['slug']);
                    }
                    $status->fill($payload)->save();
                } else {
                    // уникальный slug
                    $base = preg_replace('/[^a-z0-9_]+/i', '_', strtolower($payload['slug'])) ?: 'status';
                    $slug = $base;
                    $n = 1;
                    while (WorkflowStatus::query()->where('slug', $slug)->exists()) {
                        $slug = $base.'_'.$n++;
                    }
                    $payload['slug'] = $slug;
                    $status = WorkflowStatus::query()->create($payload);
                }

                $keepIds[] = $status->id;
                if ($clientId !== null && $clientId !== '') {
                    $idMap[(string) $clientId] = $status->id;
                }
                $idMap[(string) $status->id] = $status->id;
            }

            if ($keepIds !== []) {
                WorkflowStatus::query()->whereNotIn('id', $keepIds)->update(['is_active' => false]);
            }

            WorkflowTransition::query()->delete();
            foreach ($transitions as $i => $edge) {
                $fromRaw = (string) ($edge['from'] ?? '');
                $toRaw = (string) ($edge['to'] ?? '');
                $from = (int) ($idMap[$fromRaw] ?? (is_numeric($fromRaw) ? $fromRaw : 0));
                $to = (int) ($idMap[$toRaw] ?? (is_numeric($toRaw) ? $toRaw : 0));
                if (! $from || ! $to || $from === $to) {
                    continue;
                }
                if (! in_array($from, $keepIds, true) || ! in_array($to, $keepIds, true)) {
                    continue;
                }
                WorkflowTransition::query()->create([
                    'from_status_id' => $from,
                    'to_status_id' => $to,
                    'name' => $edge['name'] ?? null,
                    'sort_order' => $i,
                ]);
            }
        });
    }

    public function ensureBoardColumns(Board $board): void
    {
        if ($board->relationLoaded('columns')) {
            if ($board->columns->isNotEmpty()) {
                return;
            }
        } elseif ($board->columns()->exists()) {
            return;
        }

        $statuses = WorkflowStatus::query()
            ->where('is_active', true)
            ->whereIn('category', ['todo', 'in_progress', 'done'])
            ->orderBy('sort_order')
            ->get();

        foreach ($statuses as $i => $status) {
            if (! in_array($status->slug, ['new', 'in_progress', 'testing_stage', 'testing_prod', 'demo', 'completed'], true)) {
                continue;
            }
            $board->columns()->create([
                'status_id' => $status->id,
                'sort_order' => $i,
            ]);
        }
    }
}
