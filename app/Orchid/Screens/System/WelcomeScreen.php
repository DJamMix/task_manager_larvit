<?php

namespace App\Orchid\Screens\System;

use App\CoreLayer\Enums\TaskPriorityEnum;
use App\CoreLayer\Enums\TaskStatusEnum;
use App\Models\Task;
use App\Orchid\Layouts\Dashboard\HoursLineChart;
use App\Orchid\Layouts\Dashboard\PriorityBarChart;
use App\Orchid\Layouts\Dashboard\ProjectHoursBarChart;
use App\Orchid\Layouts\Dashboard\StatusPieChart;
use App\Orchid\Layouts\Dashboard\ThroughputLineChart;
use App\Orchid\Layouts\Dashboard\WorkloadBarChart;
use App\Services\DashboardAnalyticsService;
use App\Services\ProjectContext;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Screen;
use Orchid\Screen\TD;
use Orchid\Support\Facades\Layout;

class WelcomeScreen extends Screen
{
    public function query(DashboardAnalyticsService $analytics, ProjectContext $context): iterable
    {
        $user = auth()->user();
        $data = $analytics->forUser($user);

        return array_merge($data, [
            'user' => $user,
            'activeProject' => $context->project(),
            'metrics' => $data['metrics'],
        ]);
    }

    public function name(): ?string
    {
        $project = app(ProjectContext::class)->project();

        return $project
            ? 'Аналитика — ' . $project->name
            : 'Аналитика';
    }

    public function description(): ?string
    {
        return 'Сводка по задачам, времени, нагрузке и узким местам. Контекст проекта — в меню слева.';
    }

    public function commandBar(): iterable
    {
        $user = auth()->user();
        $buttons = [];

        if ($user?->hasAccess('platform.systems.my_tasks')) {
            $buttons[] = Link::make('Мои задачи')
                ->icon('bs.journal-check')
                ->route('platform.systems.my_tasks');
            $buttons[] = Link::make('Входящие')
                ->icon('bs.inbox')
                ->route('platform.systems.inbox');
        }

        if ($user?->hasAccess('platform.systems.tasks')) {
            $buttons[] = Link::make('Все задачи')
                ->icon('bs.card-checklist')
                ->route('platform.systems.tasks');
        }

        if ($user?->hasAccess('platform.systems.acts')) {
            $buttons[] = Link::make('Акты')
                ->icon('bs.journal-text')
                ->route('platform.systems.acts');
        }

        return $buttons;
    }

    public function layout(): iterable
    {
        $user = auth()->user();
        $isAdmin = $user?->hasAccess('platform.systems.tasks')
            || $user?->hasAccess('platform.systems.projects');

        $layouts = [
            Layout::view('partials.project-context-banner'),
            Layout::view('orchid.layouts.dashboard-header'),

            Layout::metrics([
                'Активные' => 'metrics.active',
                'В работе' => 'metrics.in_progress',
                'Просрочено' => 'metrics.overdue',
                'Закрыто за месяц' => 'metrics.completed_month',
                'Часы за неделю' => 'metrics.hours_week',
                'Часы за месяц' => 'metrics.hours_month',
            ]),

            Layout::metrics([
                'Ждут клиента' => 'metrics.waiting_client',
                'Ждут команду' => 'metrics.waiting_team',
                'Проекты' => 'metrics.projects',
                'Всего задач' => 'metrics.total',
                'Факт / оценка' => 'metrics.estimate_ratio',
                'Списано всего' => 'metrics.spent_total',
            ]),

            Layout::view('orchid.layouts.dashboard-pipeline'),

            Layout::columns([
                StatusPieChart::make('chart_status', 'Распределение по статусам')
                    ->description('Текущий пайплайн задач'),
                PriorityBarChart::make('chart_priority', 'По приоритетам')
                    ->description('P0–P5: где сосредоточена нагрузка'),
            ]),

            Layout::columns([
                HoursLineChart::make('chart_hours', 'Трекинг времени (14 дней)')
                    ->description('Фактически списанные часы по дням'),
                ThroughputLineChart::make('chart_throughput', 'Создано vs закрыто (8 недель)')
                    ->description('Пропускная способность команды'),
            ]),
        ];

        if ($isAdmin) {
            $layouts[] = Layout::columns([
                WorkloadBarChart::make('chart_workload', 'Нагрузка исполнителей')
                    ->description('Активные задачи по людям'),
                ProjectHoursBarChart::make('chart_projects_hours', 'Часы по проектам (30 дней)')
                    ->description('Куда уходит время'),
            ]);
        } else {
            $layouts[] = ProjectHoursBarChart::make('chart_projects_hours', 'Часы по проектам (30 дней)')
                ->description('Куда уходит время');
        }

        $layouts[] = Layout::view('orchid.layouts.dashboard-insights');

        $layouts[] = Layout::tabs([
            'Просроченные' => Layout::table('overdue_tasks', [
                TD::make('name', 'Задача')
                    ->render(fn (Task $task) => $this->taskLink($task)),
                TD::make('project', 'Проект')
                    ->render(fn (Task $task) => e($task->project?->name ?? '—')),
                TD::make('executor', 'Исполнитель')
                    ->render(fn (Task $task) => e($task->executor?->name ?? '—')),
                TD::make('priority', 'Приоритет')
                    ->render(function (Task $task) {
                        $p = TaskPriorityEnum::tryFrom((string) $task->priority);

                        return $p ? $p->badgeHtml() : '—';
                    }),
                TD::make('end_datetime', 'Дедлайн')
                    ->render(fn (Task $task) => $task->end_datetime
                        ? $task->end_datetime->format('d.m.Y H:i')
                        : '—'),
                TD::make('status', 'Статус')
                    ->render(fn (Task $task) => TaskStatusEnum::tryFrom($task->status)?->label() ?? $task->status),
            ]),
            'Недавние изменения' => Layout::table('recent_activity', [
                TD::make('updated_at', 'Когда')
                    ->render(fn (Task $task) => $task->updated_at?->format('d.m.Y H:i') ?? '—'),
                TD::make('name', 'Задача')
                    ->render(fn (Task $task) => $this->taskLink($task)),
                TD::make('project', 'Проект')
                    ->render(fn (Task $task) => e($task->project?->name ?? '—')),
                TD::make('status', 'Статус')
                    ->render(fn (Task $task) => TaskStatusEnum::tryFrom($task->status)?->label() ?? $task->status),
                TD::make('executor', 'Исполнитель')
                    ->render(fn (Task $task) => e($task->executor?->name ?? '—')),
            ]),
        ]);

        return $layouts;
    }

    private function taskLink(Task $task): string
    {
        $user = auth()->user();

        if ($user?->hasAccess('platform.systems.tasks')) {
            return (string) Link::make($task->name)->route('platform.systems.tasks.edit', $task);
        }

        if ($user?->hasAccess('platform.systems.my_tasks') && (int) $task->executor_id === (int) $user->id) {
            return (string) Link::make($task->name)->route('platform.systems.my_tasks.view', $task);
        }

        if ($user?->hasAccess('platform.systems.client.project.tasks.view') && $task->project_id) {
            return (string) Link::make($task->name)
                ->route('platform.systems.client.project.tasks.view', [
                    'project' => $task->project_id,
                    'task' => $task->id,
                ]);
        }

        return e($task->name);
    }
}
