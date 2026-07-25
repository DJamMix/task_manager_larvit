<?php

namespace App\Orchid\Screens\Acts;

use App\Models\Act;
use App\Models\Project;
use App\Services\ActService;
use App\Services\ProjectContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Screen;
use Orchid\Support\Color;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;
use WordForLaravel\Facades\WordForLaravel;

class ActEditScreen extends Screen
{
    public $act;

    public $exists = false;

    public function permission(): ?iterable
    {
        return ['platform.systems.acts'];
    }

    public function query(Act $act, ActService $acts, ProjectContext $context, Request $request): iterable
    {
        $this->exists = $act->exists;
        $this->act = $act;

        if ($act->exists) {
            $act->load(['tasks.executor', 'project.clients']);
            $projectId = (int) ($act->project_id ?: $request->integer('project_id'));
        } else {
            $projectId = (int) ($request->integer('project_id')
                ?: ($context->has() ? $context->id() : 0));
        }

        $projects = Project::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $project = $projectId > 0
            ? Project::query()->with('clients')->find($projectId)
            : null;

        $tasks = $project
            ? $acts->tasksForComposer((int) $project->id, $act->exists ? $act : null)
            : [];

        $header = [
            'number' => old('act.number', $act->exists ? $act->number : $acts->suggestNumber($projectId ?: null)),
            'date' => old('act.date', $act->exists && $act->date
                ? $act->date->format('Y-m-d')
                : now()->format('Y-m-d')),
            'customer' => old('act.customer', $act->exists ? $act->customer : $acts->defaultCustomer($project)),
            'executor' => old('act.executor', $act->exists ? $act->executor : $acts->defaultExecutor()),
            'info' => old('act.info', $act->exists ? (string) $act->info : ''),
            'project_id' => old('project_id', $projectId ?: ''),
        ];

        return [
            'act' => $act,
            'act_exists' => $act->exists,
            'header' => $header,
            'projects' => $projects,
            'project' => $project,
            'tasks' => $tasks,
            'selected_count' => collect($tasks)->where('selected', true)->count(),
            'selected_hours' => round((float) collect($tasks)->where('selected', true)->sum('hours'), 2),
        ];
    }

    public function name(): ?string
    {
        return $this->exists
            ? ('Акт ' . ($this->act->number ?? ''))
            : 'Новый акт';
    }

    public function description(): ?string
    {
        return 'Выберите задачи, скорректируйте часы и сохраните. Часы по умолчанию — факт (если есть), иначе оценка.';
    }

    public function commandBar(): iterable
    {
        $bar = [
            Link::make('К списку')
                ->icon('bs.arrow-left')
                ->route('platform.systems.acts'),
        ];

        if ($this->exists) {
            $bar[] = Link::make('Скачать Word')
                ->icon('bs.download')
                ->route('platform.systems.acts.download', $this->act)
                ->target('_blank');

            $bar[] = Button::make('Удалить')
                ->icon('bs.trash')
                ->method('remove')
                ->confirm('Удалить акт безвозвратно?')
                ->type(Color::DANGER);
        }

        $bar[] = Button::make($this->exists ? 'Сохранить' : 'Создать акт')
            ->icon('bs.check-lg')
            ->method('save')
            ->type(Color::PRIMARY)
            ->class('btn btn-primary');

        return $bar;
    }

    public function layout(): iterable
    {
        return [
            Layout::view('partials.project-context-banner'),
            Layout::view('orchid.layouts.act-composer'),
        ];
    }

    public function save(Request $request, Act $act, ActService $acts)
    {
        $actId = $act->exists ? $act->id : null;

        $validated = $request->validate([
            'act.number' => [
                'required',
                'string',
                'max:100',
                Rule::unique('acts', 'number')->ignore($actId),
            ],
            'act.date' => 'required|date',
            'act.customer' => 'required|string|max:255',
            'act.executor' => 'required|string|max:255',
            'act.info' => 'nullable|string|max:5000',
            'project_id' => 'required|exists:projects,id',
            'lines' => 'nullable|array',
        ], [
            'act.number.required' => 'Укажите номер акта',
            'act.number.unique' => 'Акт с таким номером уже существует',
            'act.date.required' => 'Укажите дату',
            'act.customer.required' => 'Укажите заказчика',
            'act.executor.required' => 'Укажите исполнителя',
            'project_id.required' => 'Выберите проект',
        ]);

        try {
            $parsed = $acts->parseLines($request->input('lines', []));
            $saved = $acts->save(
                $act->exists ? $act : null,
                [
                    'number' => $validated['act']['number'],
                    'date' => $validated['act']['date'],
                    'customer' => $validated['act']['customer'],
                    'executor' => $validated['act']['executor'],
                    'info' => $validated['act']['info'] ?? '',
                    'project_id' => (int) $validated['project_id'],
                ],
                $parsed
            );

            $dupes = $acts->duplicateWarnings($parsed['taskIds'], (int) $saved->id);
            if ($dupes !== []) {
                $nums = collect($dupes)->pluck('number')->implode(', ');
                Toast::warning('Акт сохранён. Часть задач уже есть в других актах: ' . $nums);
            } else {
                Toast::success($act->exists ? 'Акт обновлён' : 'Акт создан');
            }

            return redirect()->route('platform.systems.acts.edit', $saved);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Act save failed', ['error' => $e->getMessage()]);
            Toast::error('Не удалось сохранить акт: ' . $e->getMessage());

            return back()->withInput();
        }
    }

    public function remove(Act $act)
    {
        abort_unless($act->exists, 404);

        $act->tasks()->detach();
        $act->delete();
        Toast::info('Акт удалён');

        return redirect()->route('platform.systems.acts');
    }

    public function downloadWord(Act $act)
    {
        try {
            $act->load(['tasks', 'project']);

            $tasksData = [];
            $totalHours = 0.0;

            foreach ($act->tasks as $task) {
                $hours = round((float) ($task->pivot->hours ?? 0), 2);
                $totalHours += $hours;
                $tasksData[] = [
                    'name' => (string) ($task->name ?? 'Услуга'),
                    'hours' => $hours,
                ];
            }
            $totalHours = round($totalHours, 2);

            $info = trim((string) ($act->info ?? ''));
            // Если в комментарии похоже на реквизиты договора — вынесем в contract_ref
            $contractRef = '';
            if ($info !== '' && preg_match('/договор/iu', $info)) {
                $contractRef = $info;
                $info = '';
            }

            $data = [
                'act' => [
                    'number' => (string) $act->number,
                    'date' => $act->date,
                    'date_long' => \App\Support\ActDocumentFormatter::dateLong($act->date),
                    'city' => 'г. ________',
                    'customer' => (string) $act->customer,
                    'executor' => (string) $act->executor,
                    'info' => $info,
                    'contract_ref' => $contractRef,
                    'hours_text' => \App\Support\ActDocumentFormatter::hoursWithWords($totalHours),
                    'project' => $act->project?->name,
                ],
                'tasks' => $tasksData,
                'total_hours' => $totalHours,
            ];

            $fileName = 'Akt_' . preg_replace('/[^\w\-]+/u', '_', (string) $act->number) . '.docx';

            return WordForLaravel::load('word.act', $data)->download($fileName);
        } catch (\Throwable $e) {
            Log::error('Act download failed', ['error' => $e->getMessage(), 'act_id' => $act->id]);
            Toast::error('Ошибка генерации документа: ' . $e->getMessage());

            return back();
        }
    }
}
