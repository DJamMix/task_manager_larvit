<?php

namespace App\Orchid\Screens\Tracker;

use App\Models\Board;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Task;
use App\Services\WorkflowService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Actions\ModalToggle;
use Orchid\Screen\Fields\DateTimer;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\Select;
use Orchid\Screen\Fields\TextArea;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;

class SprintListScreen extends Screen
{
    public function query(WorkflowService $workflows, Request $request): iterable
    {
        config(['platform.workspace' => 'platform::workspace.full']);
        $workflows->bootstrapDefaults($request->user());

        $sprints = Sprint::query()
            ->with(['board', 'project'])
            ->withCount('tasks')
            ->orderByRaw("CASE status WHEN 'active' THEN 0 WHEN 'planned' THEN 1 ELSE 2 END")
            ->orderByDesc('id')
            ->get();

        $active = $sprints->firstWhere('status', 'active');
        $planned = $sprints->where('status', 'planned')->values();
        $closed = $sprints->where('status', 'closed')->values();

        $backlog = Task::query()
            ->with(['executor', 'workflowStatus', 'queue', 'project'])
            ->whereNull('sprint_id')
            ->whereNotIn('status', ['completed', 'canceled'])
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        $activeTasks = $active
            ? Task::query()
                ->with(['executor', 'workflowStatus', 'queue'])
                ->where('sprint_id', $active->id)
                ->orderBy('board_sort')
                ->orderByDesc('id')
                ->get()
            : collect();

        return [
            'sprints' => $sprints,
            'active_sprint' => $active,
            'planned_sprints' => $planned,
            'closed_sprints' => $closed,
            'backlog_tasks' => $backlog,
            'active_tasks' => $activeTasks,
            'boards' => Board::query()->orderBy('name')->get(),
            'assign_url' => route('platform.systems.sprints.assign'),
            'csrf' => csrf_token(),
        ];
    }

    public function name(): ?string
    {
        return 'Спринты';
    }

    public function description(): ?string
    {
        return 'Планирование итераций';
    }

    public function permission(): ?iterable
    {
        return ['platform.systems.tasks'];
    }

    public function commandBar(): iterable
    {
        return [
            Link::make('Доска')->route('platform.systems.boards')->icon('bs.columns-gap'),
            ModalToggle::make('Создать спринт')
                ->modal('createSprintModal')
                ->method('createSprint')
                ->icon('bs.plus-lg')
                ->class('btn btn-primary'),
        ];
    }

    public function layout(): iterable
    {
        return [
            Layout::view('orchid.layouts.tracker-sprints'),
            Layout::modal('createSprintModal', Layout::rows([
                Input::make('sprint.name')->title('Название')->required()->placeholder('Спринт 1'),
                TextArea::make('sprint.goal')->title('Цель спринта')->rows(2),
                Select::make('sprint.board_id')
                    ->title('Доска')
                    ->fromQuery(Board::query()->orderBy('name'), 'name')
                    ->empty('Без доски'),
                Select::make('sprint.project_id')
                    ->title('Проект')
                    ->fromQuery(Project::query()->orderBy('name'), 'name')
                    ->empty('Все проекты'),
                DateTimer::make('sprint.start_date')->title('Начало')->format('Y-m-d')->allowInput(),
                DateTimer::make('sprint.end_date')->title('Окончание')->format('Y-m-d')->allowInput(),
            ]))->title('Новый спринт')->applyButton('Создать'),
        ];
    }

    public function createSprint(Request $request)
    {
        $data = $request->validate([
            'sprint.name' => 'required|string|max:160',
            'sprint.goal' => 'nullable|string|max:2000',
            'sprint.board_id' => 'nullable|integer',
            'sprint.project_id' => 'nullable|integer',
            'sprint.start_date' => 'nullable|date',
            'sprint.end_date' => 'nullable|date|after_or_equal:sprint.start_date',
        ]);

        Sprint::query()->create([
            'name' => $data['sprint']['name'],
            'goal' => $data['sprint']['goal'] ?? null,
            'board_id' => $data['sprint']['board_id'] ?? null,
            'project_id' => $data['sprint']['project_id'] ?? null,
            'start_date' => $data['sprint']['start_date'] ?? null,
            'end_date' => $data['sprint']['end_date'] ?? null,
            'status' => 'planned',
            'created_by' => $request->user()->id,
        ]);

        Toast::success('Спринт создан');

        return back();
    }

    public function startSprint(Request $request)
    {
        $sprint = Sprint::query()->findOrFail($request->integer('sprint_id'));

        if ($sprint->status === 'closed') {
            Toast::error('Закрытый спринт нельзя запустить');

            return back();
        }

        Sprint::query()
            ->where('status', 'active')
            ->when($sprint->board_id, fn ($q) => $q->where('board_id', $sprint->board_id))
            ->update(['status' => 'closed', 'end_date' => Carbon::today()]);

        $sprint->fill([
            'status' => 'active',
            'start_date' => $sprint->start_date ?: Carbon::today(),
        ])->save();

        Toast::success('Спринт «'.$sprint->name.'» запущен');

        return back();
    }

    public function completeSprint(Request $request)
    {
        $sprint = Sprint::query()->findOrFail($request->integer('sprint_id'));
        $moveIncomplete = $request->boolean('move_to_backlog', true);

        if ($moveIncomplete) {
            Task::query()
                ->where('sprint_id', $sprint->id)
                ->whereNotIn('status', ['completed', 'canceled'])
                ->update(['sprint_id' => null]);
        }

        $sprint->fill([
            'status' => 'closed',
            'end_date' => $sprint->end_date ?: Carbon::today(),
        ])->save();

        Toast::success('Спринт завершён');

        return back();
    }
}
