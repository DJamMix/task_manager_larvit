<?php

namespace App\Orchid\Screens\Tracker;

use App\CoreLayer\Enums\TaskPriorityEnum;
use App\Models\Board;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Task;
use App\Models\TaskQueue;
use App\Models\User;
use App\Services\WorkflowService;
use Illuminate\Http\Request;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Actions\ModalToggle;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\Select;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;

class BoardScreen extends Screen
{
    public $board;

    public bool $canManage = false;

    public function query(WorkflowService $workflows, Request $request): iterable
    {
        config(['platform.workspace' => 'platform::workspace.full']);

        $user = $request->user();
        abort_unless(
            $user && ($user->hasAccess('platform.systems.tasks') || $user->hasAccess('platform.systems.my_tasks')),
            403
        );

        $this->canManage = $user->hasAccess('platform.systems.tasks');
        $workflows->bootstrapDefaults($user);

        $boardId = $request->integer('board') ?: Board::query()->where('is_default', true)->value('id');
        $board = Board::query()->with(['columns.status', 'project'])->find($boardId)
            ?: Board::query()->with(['columns.status', 'project'])->first();

        if ($board) {
            $workflows->ensureBoardColumns($board);
            $board->load(['columns.status']);
        }

        // Фильтры (как в Трекере)
        $assignee = (string) $request->input('assignee', $this->canManage ? 'all' : 'me');
        if (! in_array($assignee, ['me', 'all', 'unassigned'], true) && ! ctype_digit($assignee)) {
            $assignee = $this->canManage ? 'all' : 'me';
        }

        $projectId = $request->integer('project') ?: null;
        $priority = $request->input('priority');
        $queueId = $request->integer('queue') ?: null;
        $type = $request->input('type');
        $q = trim((string) $request->input('q', ''));

        $sprintId = $request->integer('sprint') ?: null;
        $sprint = $sprintId ? Sprint::query()->find($sprintId) : null;

        $tasksQuery = Task::query()
            ->with(['executor', 'workflowStatus', 'queue', 'project', 'sprint'])
            ->when($board?->project_id && ! $projectId, fn ($qq) => $qq->where('project_id', $board->project_id))
            ->when($projectId, fn ($qq) => $qq->where('project_id', $projectId))
            ->when($sprint, fn ($qq) => $qq->where('sprint_id', $sprint->id))
            ->when($priority, fn ($qq) => $qq->where('priority', $priority))
            ->when($queueId, fn ($qq) => $qq->where('queue_id', $queueId))
            ->when($type, fn ($qq) => $qq->where('type_task', $type))
            ->when($q !== '', function ($qq) use ($q) {
                $qq->where(function ($w) use ($q) {
                    $w->where('name', 'like', '%'.$q.'%')
                        ->orWhere('id', $q)
                        ->orWhereRaw("CONCAT(COALESCE((SELECT `key` FROM task_queues WHERE task_queues.id = tasks.queue_id), ''), '-', COALESCE(queue_number, '')) LIKE ?", ['%'.$q.'%']);
                });
            });

        if ($assignee === 'me') {
            $tasksQuery->where('executor_id', $user->id);
        } elseif ($assignee === 'unassigned') {
            $tasksQuery->whereNull('executor_id');
        } elseif (ctype_digit($assignee)) {
            $tasksQuery->where('executor_id', (int) $assignee);
        }

        $tasks = $tasksQuery
            ->orderBy('board_sort')
            ->orderByDesc('id')
            ->limit(800)
            ->get();

        $taskUrl = function (Task $t) use ($user) {
            if ($user->hasAccess('platform.systems.tasks')) {
                return route('platform.systems.tasks.edit', $t);
            }

            return route('platform.systems.my_tasks.view', $t);
        };

        $columns = ($board?->columns ?? collect())->map(function ($col) use ($tasks, $taskUrl) {
            $colTasks = $tasks->filter(fn ($t) => (int) $t->status_id === (int) $col->status_id
                || (! $t->status_id && (string) $t->status === (string) $col->status?->slug));

            return [
                'id' => $col->id,
                'status_id' => $col->status_id,
                'name' => $col->displayName(),
                'color' => $col->status?->color ?? '#64748b',
                'wip_limit' => $col->wip_limit,
                'tasks' => $colTasks->values()->map(fn ($t) => [
                    'id' => $t->id,
                    'key' => $t->displayKey(),
                    'name' => $t->name,
                    'priority' => $t->priority,
                    'type' => $t->type_task,
                    'executor' => $t->executor?->displayName(),
                    'executor_initials' => $t->executor?->avatarInitials(),
                    'executor_color' => $t->executor?->avatarColor(),
                    'executor_avatar' => $t->executor?->avatarUrl() ?: '',
                    'url' => $taskUrl($t),
                    'status_id' => $t->status_id,
                ])->all(),
            ];
        })->values()->all();

        $filters = [
            'assignee' => $assignee,
            'project' => $projectId,
            'priority' => $priority,
            'queue' => $queueId,
            'type' => $type,
            'q' => $q,
            'sprint' => $sprint?->id,
            'board' => $board?->id,
        ];

        $employees = User::query()
            ->whereDoesntHave('roles', fn ($r) => $r->whereIn('slug', ['client', 'client_employer', 'client_contact']))
            ->orderBy('name')
            ->get(['id', 'name', 'position', 'avatar_path']);

        $quickFilters = $this->quickFiltersForBoard($user, $board?->id);
        $activeQuickId = $this->matchQuickFilterId($quickFilters, $filters);

        return [
            'board' => $board,
            'boards' => Board::query()->orderByDesc('is_default')->orderBy('name')->get(),
            'sprints' => Sprint::query()
                ->when($board?->id, fn ($qq) => $qq->where(function ($w) use ($board) {
                    $w->where('board_id', $board->id)->orWhereNull('board_id');
                }))
                ->orderByRaw("CASE status WHEN 'active' THEN 0 WHEN 'planned' THEN 1 ELSE 2 END")
                ->orderByDesc('id')
                ->get(),
            'active_sprint' => $sprint,
            'columns' => $columns,
            'filters' => $filters,
            'quick_filters' => $quickFilters,
            'active_quick_id' => $activeQuickId,
            'can_manage' => $this->canManage,
            'move_url' => route('platform.systems.boards.move'),
            'csrf' => csrf_token(),
            'filter_projects' => Project::query()->orderBy('name')->get(['id', 'name']),
            'filter_queues' => TaskQueue::query()->orderBy('key')->get(['id', 'key', 'name']),
            'filter_priorities' => collect(TaskPriorityEnum::cases())->mapWithKeys(
                fn (TaskPriorityEnum $p) => [$p->value => method_exists($p, 'label') ? $p->label() : $p->value]
            )->all(),
            'filter_types' => [
                'feature' => 'Задача',
                'bug' => 'Ошибка',
                'improvement' => 'Улучшение',
            ],
            'filter_assignees' => $employees->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->displayName(),
                'initials' => $u->avatarInitials(),
                'color' => $u->avatarColor(),
                'avatar' => $u->avatarUrl(),
            ])->values()->all(),
            'viewer_id' => $user->id,
            'tasks_total' => $tasks->count(),
        ];
    }

    public function saveQuickFilter(Request $request)
    {
        $user = $request->user();
        abort_unless(
            $user && ($user->hasAccess('platform.systems.tasks') || $user->hasAccess('platform.systems.my_tasks')),
            403
        );

        $data = $request->validate([
            'name' => 'required|string|max:80',
            'board_id' => 'nullable|integer',
            'params' => 'nullable|array',
            'params.assignee' => 'nullable|string|max:40',
            'params.project' => 'nullable|string|max:40',
            'params.sprint' => 'nullable|string|max:40',
            'params.priority' => 'nullable|string|max:40',
            'params.queue' => 'nullable|string|max:40',
            'params.type' => 'nullable|string|max:40',
            'params.q' => 'nullable|string|max:160',
        ]);

        $boardId = isset($data['board_id']) ? (int) $data['board_id'] : null;
        $params = collect($data['params'] ?? [])
            ->map(fn ($v) => is_string($v) ? trim($v) : $v)
            ->filter(fn ($v) => $v !== null && $v !== '')
            ->all();

        if ($boardId) {
            $params['board'] = $boardId;
        }

        $all = $user->uiPreference('board_quick_filters', []) ?: [];
        if (! is_array($all)) {
            $all = [];
        }

        $all[] = [
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'name' => trim($data['name']),
            'board_id' => $boardId,
            'params' => $params,
            'created_at' => now()->toIso8601String(),
        ];

        $user->setUiPreference('board_quick_filters', array_values($all));
        Toast::success('Фильтр сохранён');

        return redirect()->route('platform.systems.boards', $params);
    }

    public function deleteQuickFilter(Request $request)
    {
        $user = $request->user();
        abort_unless(
            $user && ($user->hasAccess('platform.systems.tasks') || $user->hasAccess('platform.systems.my_tasks')),
            403
        );

        $data = $request->validate([
            'id' => 'required|string|max:64',
            'board_id' => 'nullable|integer',
        ]);

        $all = $user->uiPreference('board_quick_filters', []) ?: [];
        if (! is_array($all)) {
            $all = [];
        }

        $all = array_values(array_filter(
            $all,
            fn ($item) => (string) ($item['id'] ?? '') !== (string) $data['id']
        ));

        $user->setUiPreference('board_quick_filters', $all);
        Toast::info('Фильтр удалён');

        return redirect()->route('platform.systems.boards', array_filter([
            'board' => $data['board_id'] ?? null,
            'assignee' => $user->hasAccess('platform.systems.tasks') ? 'all' : 'me',
        ]));
    }

    /**
     * @return list<array{id:string,name:string,board_id:?int,params:array}>
     */
    private function quickFiltersForBoard(?User $user, ?int $boardId): array
    {
        if (! $user) {
            return [];
        }

        $all = $user->uiPreference('board_quick_filters', []) ?: [];
        if (! is_array($all)) {
            return [];
        }

        return collect($all)
            ->filter(function ($item) use ($boardId) {
                if (! is_array($item) || empty($item['id']) || empty($item['name'])) {
                    return false;
                }
                $itemBoard = $item['board_id'] ?? null;

                return $itemBoard === null || (int) $itemBoard === (int) $boardId;
            })
            ->values()
            ->all();
    }

    /**
     * @param  list<array{id:string,params?:array}>  $quickFilters
     * @param  array<string, mixed>  $filters
     */
    private function matchQuickFilterId(array $quickFilters, array $filters): ?string
    {
        $normalize = function (array $params) use ($filters): array {
            $keys = ['assignee', 'project', 'sprint', 'priority', 'queue', 'type', 'q'];
            $out = [];
            foreach ($keys as $key) {
                $val = $params[$key] ?? null;
                if ($val === null || $val === '') {
                    continue;
                }
                $out[$key] = (string) $val;
            }

            return $out;
        };

        $current = $normalize([
            'assignee' => $filters['assignee'] ?? null,
            'project' => $filters['project'] ?? null,
            'sprint' => $filters['sprint'] ?? null,
            'priority' => $filters['priority'] ?? null,
            'queue' => $filters['queue'] ?? null,
            'type' => $filters['type'] ?? null,
            'q' => $filters['q'] ?? null,
        ]);

        foreach ($quickFilters as $qf) {
            $candidate = $normalize($qf['params'] ?? []);
            ksort($candidate);
            $cmp = $current;
            ksort($cmp);
            if ($candidate === $cmp) {
                return (string) $qf['id'];
            }
        }

        return null;
    }

    public function name(): ?string
    {
        return 'Доска';
    }

    public function description(): ?string
    {
        // Название доски показывается в UI переключателя — не дублируем в шапке Orchid
        return null;
    }

    public function permission(): ?iterable
    {
        return null;
    }

    /**
     * Сотрудники (my_tasks) — просмотр; админы (tasks) — полное управление.
     */
    public function checkAccess(\Illuminate\Http\Request $request): bool
    {
        $user = $request->user();

        return (bool) ($user
            && ($user->hasAccess('platform.systems.tasks') || $user->hasAccess('platform.systems.my_tasks')));
    }

    public function commandBar(): iterable
    {
        if (! $this->canManage) {
            return [];
        }

        return [
            Link::make('Спринты')->route('platform.systems.sprints')->icon('bs.lightning-charge'),
            Link::make('Workflow')->route('platform.systems.workflow')->icon('bs.diagram-3'),
            ModalToggle::make('Создать доску')
                ->modal('createBoardModal')
                ->method('createBoard')
                ->icon('bs.plus-lg'),
        ];
    }

    public function layout(): iterable
    {
        $layouts = [
            Layout::view('orchid.layouts.tracker-board'),
        ];

        if ($this->canManage) {
            $layouts[] = Layout::modal('createBoardModal', Layout::rows([
                Input::make('board.name')->title('Название')->required()->placeholder('Например, Разработка'),
                Select::make('board.project_id')
                    ->title('Проект')
                    ->fromQuery(Project::query()->orderBy('name'), 'name')
                    ->empty('Все проекты'),
                Select::make('board.type')->title('Тип')->options([
                    'kanban' => 'Kanban',
                    'scrum' => 'Scrum',
                ])->value('kanban'),
            ]))->title('Новая доска')->applyButton('Создать');
        }

        return $layouts;
    }

    public function createBoard(Request $request, WorkflowService $workflows)
    {
        abort_unless($request->user()?->hasAccess('platform.systems.tasks'), 403);

        $data = $request->validate([
            'board.name' => 'required|string|max:160',
            'board.project_id' => 'nullable|integer',
            'board.type' => 'required|in:kanban,scrum',
        ]);

        $board = Board::query()->create([
            'name' => $data['board']['name'],
            'project_id' => $data['board']['project_id'] ?? null,
            'type' => $data['board']['type'],
            'created_by' => $request->user()->id,
            'is_default' => ! Board::query()->exists(),
        ]);
        $workflows->ensureBoardColumns($board);
        Toast::success('Доска создана');

        return redirect()->route('platform.systems.boards', ['board' => $board->id]);
    }
}
