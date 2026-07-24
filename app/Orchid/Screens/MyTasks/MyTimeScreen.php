<?php

namespace App\Orchid\Screens\MyTasks;

use App\Models\TrackingTime;
use App\Services\ProjectContext;
use Illuminate\Http\Request;
use Orchid\Screen\Screen;
use Orchid\Screen\TD;
use Orchid\Support\Facades\Layout;

class MyTimeScreen extends Screen
{
    public function query(Request $request, ProjectContext $context): iterable
    {
        $from = $request->get('from', now()->startOfWeek()->format('Y-m-d'));
        $to = $request->get('to', now()->endOfWeek()->format('Y-m-d'));

        $entriesQuery = TrackingTime::with(['task.project', 'task'])
            ->where('user_id', auth()->id())
            ->whereBetween('work_date', [$from, $to])
            ->latest('work_date');

        if ($context->has()) {
            $entriesQuery->whereHas('task', fn ($q) => $q->where('project_id', $context->id()));
        }

        $entries = $entriesQuery->get();

        return [
            'entries' => $entries,
            'from' => $from,
            'to' => $to,
            'total_hours' => round((float) $entries->sum('hours_spent'), 2),
            'days' => $entries->groupBy(fn ($e) => $e->work_date->format('Y-m-d')),
        ];
    }

    public function name(): ?string
    {
        return 'Моё время';
    }

    public function description(): ?string
    {
        return 'Табель учёта времени. Трекинг не связан с оценкой задачи — это факт выполненной работы.';
    }

    public function permission(): ?iterable
    {
        return [
            'platform.systems.my_tasks',
        ];
    }

    public function commandBar(): iterable
    {
        return [];
    }

    public function layout(): iterable
    {
        return [
            Layout::view('partials.project-context-banner'),
            Layout::view('orchid.layouts.my-time'),
            Layout::table('entries', [
                TD::make('work_date', 'Дата')
                    ->render(fn (TrackingTime $entry) => $entry->work_date->format('d.m.Y')),
                TD::make('task.name', 'Задача')
                    ->render(fn (TrackingTime $entry) => $entry->task?->name ?? '—'),
                TD::make('task.project.name', 'Проект')
                    ->render(fn (TrackingTime $entry) => $entry->task?->project?->name ?? '—'),
                TD::make('hours_spent', 'Часы')
                    ->alignRight()
                    ->render(fn (TrackingTime $entry) => number_format((float) $entry->hours_spent, 2)),
                TD::make('work_description', 'Описание')
                    ->render(fn (TrackingTime $entry) => \Illuminate\Support\Str::limit($entry->work_description, 120)),
            ]),
        ];
    }
}
