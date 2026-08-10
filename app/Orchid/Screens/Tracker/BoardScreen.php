<?php

namespace App\Orchid\Screens\Tracker;

use App\Models\Board;
use App\Models\Project;
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

    public function query(WorkflowService $workflows, Request $request): iterable
    {
        config(['platform.workspace' => 'platform::workspace.full']);

        $workflows->bootstrapDefaults($request->user());

        $boardId = $request->integer('board') ?: Board::query()->where('is_default', true)->value('id');
        $board = Board::query()->with(['columns.status', 'project'])->find($boardId)
            ?: Board::query()->with(['columns.status', 'project'])->first();

        if ($board) {
            $workflows->ensureBoardColumns($board);
            $board->load(['columns.status']);
        }

        $sprintId = $request->integer('sprint') ?: null;
        $sprint = $sprintId
            ? \App\Models\Sprint::query()->find($sprintId)
            : \App\Models\Sprint::query()
                ->where('status', 'active')
                ->when($board?->id, fn ($q) => $q->where(function ($w) use ($board) {
                    $w->where('board_id', $board->id)->orWhereNull('board_id');
                }))
                ->first();

        $tasks = \App\Models\Task::query()
            ->with(['executor', 'workflowStatus', 'queue', 'project', 'sprint'])
            ->when($board?->project_id, fn ($q) => $q->where('project_id', $board->project_id))
            ->when($sprint, fn ($q) => $q->where('sprint_id', $sprint->id))
            ->orderBy('board_sort')
            ->orderByDesc('id')
            ->get();

        $columns = ($board?->columns ?? collect())->map(function ($col) use ($tasks) {
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
                    'executor' => $t->executor?->displayName(),
                    'url' => route('platform.systems.tasks.edit', $t),
                    'status_id' => $t->status_id,
                ])->all(),
            ];
        })->values()->all();

        return [
            'board' => $board,
            'boards' => Board::query()->orderByDesc('is_default')->orderBy('name')->get(),
            'sprints' => \App\Models\Sprint::query()
                ->when($board?->id, fn ($q) => $q->where(function ($w) use ($board) {
                    $w->where('board_id', $board->id)->orWhereNull('board_id');
                }))
                ->orderByRaw("CASE status WHEN 'active' THEN 0 WHEN 'planned' THEN 1 ELSE 2 END")
                ->orderByDesc('id')
                ->get(),
            'active_sprint' => $sprint,
            'columns' => $columns,
            'move_url' => route('platform.systems.boards.move'),
            'csrf' => csrf_token(),
        ];
    }

    public function name(): ?string
    {
        return 'Доска';
    }

    public function description(): ?string
    {
        return $this->board?->name ?? 'Kanban';
    }

    public function permission(): ?iterable
    {
        return ['platform.systems.tasks'];
    }

    public function commandBar(): iterable
    {
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
        return [
            Layout::view('orchid.layouts.tracker-board'),
            Layout::modal('createBoardModal', Layout::rows([
                Input::make('board.name')->title('Название')->required()->placeholder('Например, Разработка'),
                Select::make('board.project_id')
                    ->title('Проект')
                    ->fromQuery(Project::query()->orderBy('name'), 'name')
                    ->empty('Все проекты'),
                Select::make('board.type')->title('Тип')->options([
                    'kanban' => 'Kanban',
                    'scrum' => 'Scrum',
                ])->value('kanban'),
            ]))->title('Новая доска')->applyButton('Создать'),
        ];
    }

    public function createBoard(Request $request, WorkflowService $workflows)
    {
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
