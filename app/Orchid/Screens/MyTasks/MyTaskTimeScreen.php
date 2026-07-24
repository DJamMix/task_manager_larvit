<?php

namespace App\Orchid\Screens\MyTasks;

use App\Models\Task;
use App\Models\TrackingTime;
use App\Orchid\Layouts\MyTasks\HoursSpentTask;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Actions\ModalToggle;
use Orchid\Screen\Screen;
use Orchid\Screen\TD;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;

class MyTaskTimeScreen extends Screen
{
    public $task;

    public function query(Task $task): iterable
    {
        $this->authorizeView($task);

        return [
            'task' => $task,
            'timeEntries' => $task->timeEntries()->with('user')->latest('work_date')->get(),
        ];
    }

    public function name(): ?string
    {
        return 'Учёт времени — ' . ($this->task->name ?? '');
    }

    public function description(): ?string
    {
        return 'Журнал списаний. Оценка задачи здесь не меняется.';
    }

    public function permission(): ?iterable
    {
        return ['platform.systems.my_tasks'];
    }

    public function commandBar(): iterable
    {
        $buttons = [
            Link::make('К задаче')
                ->icon('bs.arrow-left')
                ->route('platform.systems.my_tasks.view', $this->task),
        ];

        if ($this->task->canTrackTime()) {
            $buttons[] = ModalToggle::make('Добавить время')
                ->modal('timeTrackingModal')
                ->method('saveTimeEntry')
                ->icon('clock')
                ->class('btn btn-primary');
        }

        return $buttons;
    }

    public function layout(): iterable
    {
        return [
            Layout::view('orchid.layouts.estimate-vs-spent'),
            Layout::table('timeEntries', [
                TD::make('work_date', 'Дата')
                    ->render(fn (TrackingTime $e) => $e->work_date?->format('d.m.Y') ?? '—'),
                TD::make('user.name', 'Кто')
                    ->render(fn (TrackingTime $e) => $e->user?->displayName() ?? '—'),
                TD::make('hours_spent', 'Часы')
                    ->alignRight()
                    ->render(fn (TrackingTime $e) => number_format((float) $e->hours_spent, 2)),
                TD::make('work_description', 'Что сделано')
                    ->render(fn (TrackingTime $e) => e(\Illuminate\Support\Str::limit($e->work_description, 160))),
            ]),
            Layout::modal('timeTrackingModal', [HoursSpentTask::class])
                ->title('Учет рабочего времени')
                ->applyButton('Сохранить'),
        ];
    }

    public function saveTimeEntry(Task $task, Request $request)
    {
        $this->authorizeView($task);

        if (!$task->canTrackTime()) {
            Toast::error('Нельзя учитывать время по этой задаче');
            return back();
        }

        $request->validate([
            'tracking.hours_spent' => 'required|numeric|min:0.25|max:24',
            'tracking.work_date' => 'required|date',
            'tracking.work_description' => 'required|string|max:2000',
        ]);

        $tracking = new TrackingTime();
        $tracking->id = Str::ulid();
        $tracking->task_id = $task->id;
        $tracking->hours_spent = $request->input('tracking.hours_spent');
        $tracking->work_description = $request->input('tracking.work_description');
        $tracking->work_date = $request->input('tracking.work_date');
        $tracking->user_id = auth()->id();
        $tracking->save();

        $task->increment('hours_spent', $request->input('tracking.hours_spent'));

        Toast::success('Время учтено');
    }

    private function authorizeView(Task $task): void
    {
        $user = auth()->user();
        // Наблюдатель не видит учёт времени — только обсуждение
        $allowed = (int) $task->executor_id === (int) $user->id
            || $user->hasAccess('platform.systems.tasks');

        if (!$allowed) {
            abort(403);
        }
    }
}
