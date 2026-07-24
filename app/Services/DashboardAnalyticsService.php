<?php

declare(strict_types=1);

namespace App\Services;

use App\CoreLayer\Enums\TaskPriorityEnum;
use App\CoreLayer\Enums\TaskStatusEnum;
use App\Models\Act;
use App\Models\Project;
use App\Models\Task;
use App\Models\TrackingTime;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DashboardAnalyticsService
{
    public function __construct(
        private readonly ProjectContext $context,
    ) {}

    public function forUser(User $user): array
    {
        $scope = $this->resolveScope($user);

        return [
            'scope' => $scope,
            'metrics' => $this->metrics($scope),
            'chart_status' => $this->chartByStatus($scope),
            'chart_priority' => $this->chartByPriority($scope),
            'chart_hours' => $this->chartHoursTrend($scope, 14),
            'chart_throughput' => $this->chartThroughput($scope, 8),
            'chart_workload' => $this->chartWorkload($scope),
            'chart_projects_hours' => $this->chartProjectHours($scope),
            'pipeline' => $this->pipeline($scope),
            'project_health' => $this->projectHealth($scope),
            'overdue_tasks' => $this->overdueTasks($scope),
            'bottlenecks' => $this->bottlenecks($scope),
            'estimate_accuracy' => $this->estimateAccuracy($scope),
            'top_executors' => $this->topExecutors($scope),
            'recent_activity' => $this->recentActivity($scope),
            'acts_summary' => $this->actsSummary($scope, $user),
        ];
    }

    private function resolveScope(User $user): array
    {
        $isAdmin = $user->hasAccess('platform.systems.tasks')
            || $user->hasAccess('platform.systems.projects')
            || $user->hasAccess('platform.systems.users');

        $isEmployee = $user->hasAccess('platform.systems.my_tasks');
        $isClient = $user->hasAccess('platform.systems.client.projects');

        $role = $isAdmin ? 'admin' : ($isEmployee ? 'employee' : ($isClient ? 'client' : 'user'));

        return [
            'role' => $role,
            'user_id' => $user->id,
            'project_id' => $this->context->id(),
            'project' => $this->context->project(),
            'is_admin' => $isAdmin,
        ];
    }

    private function tasksQuery(array $scope): Builder
    {
        $query = Task::query();

        if (!empty($scope['project_id'])) {
            $query->where('project_id', $scope['project_id']);
        }

        if ($scope['role'] === 'employee') {
            $query->where('executor_id', $scope['user_id']);
        }

        if ($scope['role'] === 'client') {
            $projectIds = User::find($scope['user_id'])?->projects()->pluck('projects.id') ?? collect();
            $query->whereIn('project_id', $projectIds);
        }

        return $query;
    }

    private function trackingQuery(array $scope): Builder
    {
        $query = TrackingTime::query()->whereHas('task', function (Builder $q) use ($scope) {
            if (!empty($scope['project_id'])) {
                $q->where('project_id', $scope['project_id']);
            }
            if ($scope['role'] === 'client') {
                $projectIds = User::find($scope['user_id'])?->projects()->pluck('projects.id') ?? collect();
                $q->whereIn('project_id', $projectIds);
            }
        });

        if ($scope['role'] === 'employee') {
            $query->where('user_id', $scope['user_id']);
        }

        return $query;
    }

    private function metrics(array $scope): array
    {
        $base = $this->tasksQuery($scope);
        $done = [
            TaskStatusEnum::COMPLETED->value,
            TaskStatusEnum::CANCELED->value,
            TaskStatusEnum::UNPAID->value,
        ];

        $active = (clone $base)->whereNotIn('status', $done)->count();
        $total = (clone $base)->count();
        $completedMonth = (clone $base)
            ->where('status', TaskStatusEnum::COMPLETED->value)
            ->where('updated_at', '>=', now()->startOfMonth())
            ->count();

        $overdue = (clone $base)
            ->whereNotNull('end_datetime')
            ->where('end_datetime', '<', now())
            ->whereNotIn('status', $done)
            ->count();

        $waitingClient = (clone $base)->whereIn('status', [
            TaskStatusEnum::DRAFT->value,
            TaskStatusEnum::ESTIMATION_REVIEW->value,
            TaskStatusEnum::DEMO->value,
        ])->count();

        $waitingTeam = (clone $base)->whereIn('status', [
            TaskStatusEnum::ESTIMATION->value,
            TaskStatusEnum::NEW->value,
        ])->count();

        $inProgress = (clone $base)->where('status', TaskStatusEnum::IN_PROGRESS->value)->count();

        $hoursWeek = (float) (clone $this->trackingQuery($scope))
            ->whereBetween('work_date', [now()->startOfWeek()->toDateString(), now()->toDateString()])
            ->sum('hours_spent');

        $hoursMonth = (float) (clone $this->trackingQuery($scope))
            ->whereBetween('work_date', [now()->startOfMonth()->toDateString(), now()->toDateString()])
            ->sum('hours_spent');

        $estimated = (float) (clone $base)->sum('estimation_hours');
        $spent = (float) (clone $base)->sum('hours_spent');
        $accuracy = $estimated > 0 ? round(($spent / $estimated) * 100, 1) : 0;

        $projectsCount = $this->projectsQuery($scope)->count();

        $prevWeekHours = (float) (clone $this->trackingQuery($scope))
            ->whereBetween('work_date', [
                now()->subWeek()->startOfWeek()->toDateString(),
                now()->subWeek()->endOfWeek()->toDateString(),
            ])
            ->sum('hours_spent');

        $hoursDiff = $prevWeekHours > 0
            ? round((($hoursWeek - $prevWeekHours) / $prevWeekHours) * 100, 1)
            : 0.0;

        return [
            'active' => ['value' => $active],
            'overdue' => ['value' => $overdue],
            'in_progress' => ['value' => $inProgress],
            'completed_month' => ['value' => $completedMonth],
            'hours_week' => [
                'value' => number_format($hoursWeek, 1) . ' ч',
                'diff' => $hoursDiff,
            ],
            'hours_month' => ['value' => number_format($hoursMonth, 1) . ' ч'],
            'waiting_client' => ['value' => $waitingClient],
            'waiting_team' => ['value' => $waitingTeam],
            'projects' => ['value' => $projectsCount],
            'total' => ['value' => $total],
            'estimate_ratio' => ['value' => $accuracy . '%'],
            'spent_total' => ['value' => number_format($spent, 1) . ' ч'],
        ];
    }

    private function projectsQuery(array $scope): Builder
    {
        $query = Project::query();

        if (Schema::hasColumn('projects', 'is_active')) {
            $query->where('is_active', true);
        }

        if (!empty($scope['project_id'])) {
            $query->where('id', $scope['project_id']);
        }

        if ($scope['role'] === 'client') {
            $projectIds = User::find($scope['user_id'])?->projects()->pluck('projects.id') ?? collect();
            $query->whereIn('id', $projectIds);
        }

        if ($scope['role'] === 'employee') {
            $ids = Task::where('executor_id', $scope['user_id'])->distinct()->pluck('project_id');
            $query->whereIn('id', $ids);
        }

        return $query;
    }

    private function chartByStatus(array $scope): array
    {
        $rows = $this->tasksQuery($scope)
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $labels = [];
        $values = [];

        foreach (TaskStatusEnum::cases() as $status) {
            $count = (int) ($rows[$status->value] ?? 0);
            if ($count === 0) {
                continue;
            }
            $labels[] = $status->label();
            $values[] = $count;
        }

        if (empty($values)) {
            return [['name' => 'Статусы', 'values' => [0], 'labels' => ['Нет данных']]];
        }

        return [['name' => 'Задачи', 'values' => $values, 'labels' => $labels]];
    }

    private function chartByPriority(array $scope): array
    {
        $rows = $this->tasksQuery($scope)
            ->select('priority', DB::raw('COUNT(*) as total'))
            ->groupBy('priority')
            ->pluck('total', 'priority');

        $labels = [];
        $values = [];

        foreach (TaskPriorityEnum::orderedCases() as $priority) {
            $labels[] = $priority->code() . ' ' . $priority->label();
            $values[] = (int) ($rows[$priority->value] ?? 0);
        }

        return [['name' => 'Приоритет', 'values' => $values, 'labels' => $labels]];
    }

    private function chartHoursTrend(array $scope, int $days): array
    {
        $from = now()->subDays($days - 1)->startOfDay();
        $to = now()->endOfDay();

        $rows = (clone $this->trackingQuery($scope))
            ->whereBetween('work_date', [$from->toDateString(), $to->toDateString()])
            ->select(DB::raw('DATE(work_date) as day'), DB::raw('SUM(hours_spent) as total'))
            ->groupBy('day')
            ->pluck('total', 'day');

        $labels = [];
        $values = [];

        foreach (CarbonPeriod::create($from, $to) as $day) {
            /** @var Carbon $day */
            $key = $day->format('Y-m-d');
            $labels[] = $day->format('d.m');
            $values[] = round((float) ($rows[$key] ?? 0), 2);
        }

        return [['name' => 'Часы', 'values' => $values, 'labels' => $labels]];
    }

    private function chartThroughput(array $scope, int $weeks): array
    {
        $labels = [];
        $created = [];
        $completed = [];

        for ($i = $weeks - 1; $i >= 0; $i--) {
            $start = now()->subWeeks($i)->startOfWeek();
            $end = now()->subWeeks($i)->endOfWeek();
            $labels[] = $start->format('d.m') . '–' . $end->format('d.m');

            $created[] = (clone $this->tasksQuery($scope))
                ->whereBetween('created_at', [$start, $end])
                ->count();

            $completed[] = (clone $this->tasksQuery($scope))
                ->where('status', TaskStatusEnum::COMPLETED->value)
                ->whereBetween('updated_at', [$start, $end])
                ->count();
        }

        return [
            ['name' => 'Создано', 'values' => $created, 'labels' => $labels],
            ['name' => 'Закрыто', 'values' => $completed, 'labels' => $labels],
        ];
    }

    private function chartWorkload(array $scope): array
    {
        if ($scope['role'] === 'employee') {
            return [['name' => 'Нагрузка', 'values' => [0], 'labels' => ['—']]];
        }

        $done = [
            TaskStatusEnum::COMPLETED->value,
            TaskStatusEnum::CANCELED->value,
            TaskStatusEnum::UNPAID->value,
        ];

        $rows = $this->tasksQuery($scope)
            ->whereNotNull('executor_id')
            ->whereNotIn('status', $done)
            ->select('executor_id', DB::raw('COUNT(*) as total'))
            ->groupBy('executor_id')
            ->orderByDesc('total')
            ->limit(8)
            ->get();

        $users = User::whereIn('id', $rows->pluck('executor_id'))->pluck('name', 'id');

        $labels = [];
        $values = [];
        foreach ($rows as $row) {
            $labels[] = $users[$row->executor_id] ?? ('#' . $row->executor_id);
            $values[] = (int) $row->total;
        }

        if (empty($values)) {
            return [['name' => 'Активные задачи', 'values' => [0], 'labels' => ['Нет данных']]];
        }

        return [['name' => 'Активные задачи', 'values' => $values, 'labels' => $labels]];
    }

    private function chartProjectHours(array $scope): array
    {
        $query = TrackingTime::query()
            ->join('tasks', 'tracking_times.task_id', '=', 'tasks.id')
            ->join('projects', 'tasks.project_id', '=', 'projects.id')
            ->where('tracking_times.work_date', '>=', now()->subDays(30)->toDateString());

        if (!empty($scope['project_id'])) {
            $query->where('tasks.project_id', $scope['project_id']);
        }

        if ($scope['role'] === 'employee') {
            $query->where('tracking_times.user_id', $scope['user_id']);
        }

        if ($scope['role'] === 'client') {
            $projectIds = User::find($scope['user_id'])?->projects()->pluck('projects.id') ?? collect();
            $query->whereIn('tasks.project_id', $projectIds);
        }

        $rows = $query
            ->select('projects.name', DB::raw('SUM(tracking_times.hours_spent) as total'))
            ->groupBy('projects.id', 'projects.name')
            ->orderByDesc('total')
            ->limit(8)
            ->get();

        if ($rows->isEmpty()) {
            return [['name' => 'Часы', 'values' => [0], 'labels' => ['Нет данных']]];
        }

        return [[
            'name' => 'Часы за 30 дней',
            'values' => $rows->map(fn ($r) => round((float) $r->total, 1))->all(),
            'labels' => $rows->pluck('name')->all(),
        ]];
    }

    private function pipeline(array $scope): array
    {
        $groups = [
            'Согласование' => [TaskStatusEnum::DRAFT->value, TaskStatusEnum::APPROVED->value],
            'Оценка' => [TaskStatusEnum::ESTIMATION->value, TaskStatusEnum::ESTIMATION_REVIEW->value],
            'Очередь' => [TaskStatusEnum::NEW->value],
            'В работе' => [TaskStatusEnum::IN_PROGRESS->value],
            'Тест' => [TaskStatusEnum::TESTING_STAGE->value, TaskStatusEnum::TESTING_PROD->value],
            'Демо' => [TaskStatusEnum::DEMO->value],
            'Оплата/закрытие' => [TaskStatusEnum::UNPAID->value, TaskStatusEnum::COMPLETED->value],
        ];

        $result = [];
        foreach ($groups as $label => $statuses) {
            $result[] = [
                'label' => $label,
                'count' => (clone $this->tasksQuery($scope))->whereIn('status', $statuses)->count(),
            ];
        }

        return $result;
    }

    private function projectHealth(array $scope): Collection
    {
        $done = [
            TaskStatusEnum::COMPLETED->value,
            TaskStatusEnum::CANCELED->value,
            TaskStatusEnum::UNPAID->value,
        ];

        return $this->projectsQuery($scope)
            ->withCount([
                'tasks',
                'tasks as active_tasks_count' => fn ($q) => $q->whereNotIn('status', $done),
                'tasks as overdue_tasks_count' => fn ($q) => $q
                    ->whereNotNull('end_datetime')
                    ->where('end_datetime', '<', now())
                    ->whereNotIn('status', $done),
            ])
            ->withSum('tasks as hours_spent_sum', 'hours_spent')
            ->withSum('tasks as hours_estimated_sum', 'estimation_hours')
            ->orderByDesc('active_tasks_count')
            ->limit(10)
            ->get()
            ->map(function (Project $project) {
                $total = (int) $project->tasks_count;
                $active = (int) $project->active_tasks_count;
                $doneCount = max(0, $total - $active);
                $percent = $total > 0 ? (int) round(($doneCount / $total) * 100) : 0;

                return [
                    'id' => $project->id,
                    'name' => $project->name,
                    'total' => $total,
                    'active' => $active,
                    'overdue' => (int) $project->overdue_tasks_count,
                    'percent' => $percent,
                    'spent' => round((float) ($project->hours_spent_sum ?? 0), 1),
                    'estimated' => round((float) ($project->hours_estimated_sum ?? 0), 1),
                ];
            });
    }

    private function overdueTasks(array $scope): Collection
    {
        $done = [
            TaskStatusEnum::COMPLETED->value,
            TaskStatusEnum::CANCELED->value,
            TaskStatusEnum::UNPAID->value,
        ];

        return $this->tasksQuery($scope)
            ->with(['project', 'executor'])
            ->whereNotNull('end_datetime')
            ->where('end_datetime', '<', now())
            ->whereNotIn('status', $done)
            ->orderBy('end_datetime')
            ->limit(10)
            ->get();
    }

    private function bottlenecks(array $scope): array
    {
        $watch = [
            TaskStatusEnum::DRAFT->value,
            TaskStatusEnum::ESTIMATION->value,
            TaskStatusEnum::ESTIMATION_REVIEW->value,
            TaskStatusEnum::NEW->value,
            TaskStatusEnum::TESTING_STAGE->value,
            TaskStatusEnum::TESTING_PROD->value,
            TaskStatusEnum::DEMO->value,
            TaskStatusEnum::UNPAID->value,
        ];

        $rows = $this->tasksQuery($scope)
            ->whereIn('status', $watch)
            ->select('status', DB::raw('COUNT(*) as total'), DB::raw('AVG(TIMESTAMPDIFF(HOUR, updated_at, NOW())) as avg_hours'))
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $result = [];
        foreach ($watch as $status) {
            $row = $rows->get($status);
            if (!$row || (int) $row->total === 0) {
                continue;
            }

            $enum = TaskStatusEnum::tryFrom($status);
            $result[] = [
                'status' => $status,
                'label' => $enum?->label() ?? $status,
                'count' => (int) $row->total,
                'avg_days' => round(((float) $row->avg_hours) / 24, 1),
            ];
        }

        usort($result, fn ($a, $b) => $b['count'] <=> $a['count']);

        return $result;
    }

    private function estimateAccuracy(array $scope): array
    {
        $tasks = $this->tasksQuery($scope)
            ->where('estimation_hours', '>', 0)
            ->where('hours_spent', '>', 0)
            ->get(['estimation_hours', 'hours_spent']);

        if ($tasks->isEmpty()) {
            return [
                'sample' => 0,
                'avg_ratio' => 0,
                'under' => 0,
                'over' => 0,
                'on_track' => 0,
            ];
        }

        $ratios = $tasks->map(fn ($t) => (float) $t->hours_spent / (float) $t->estimation_hours);
        $under = $ratios->filter(fn ($r) => $r < 0.9)->count();
        $over = $ratios->filter(fn ($r) => $r > 1.1)->count();
        $onTrack = $tasks->count() - $under - $over;

        return [
            'sample' => $tasks->count(),
            'avg_ratio' => round($ratios->avg() * 100, 1),
            'under' => $under,
            'over' => $over,
            'on_track' => $onTrack,
        ];
    }

    private function topExecutors(array $scope): Collection
    {
        if ($scope['role'] === 'employee') {
            return collect();
        }

        $from = now()->subDays(30)->toDateString();

        $query = TrackingTime::query()
            ->join('users', 'tracking_times.user_id', '=', 'users.id')
            ->join('tasks', 'tracking_times.task_id', '=', 'tasks.id')
            ->where('tracking_times.work_date', '>=', $from);

        if (!empty($scope['project_id'])) {
            $query->where('tasks.project_id', $scope['project_id']);
        }

        return $query
            ->select(
                'users.id',
                'users.name',
                DB::raw('SUM(tracking_times.hours_spent) as hours'),
                DB::raw('COUNT(DISTINCT tracking_times.task_id) as tasks')
            )
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('hours')
            ->limit(8)
            ->get()
            ->map(fn ($row) => [
                'id' => $row->id,
                'name' => $row->name,
                'hours' => round((float) $row->hours, 1),
                'tasks' => (int) $row->tasks,
            ]);
    }

    private function recentActivity(array $scope): Collection
    {
        return $this->tasksQuery($scope)
            ->with(['project', 'executor'])
            ->orderByDesc('updated_at')
            ->limit(12)
            ->get();
    }

    private function actsSummary(array $scope, User $user): array
    {
        if (!$user->hasAccess('platform.systems.acts')) {
            return ['visible' => false];
        }

        $query = Act::query();
        if (!empty($scope['project_id']) && Schema::hasColumn('acts', 'project_id')) {
            $query->where('project_id', $scope['project_id']);
        }

        $month = (clone $query)->where('date', '>=', now()->startOfMonth()->toDateString());

        return [
            'visible' => true,
            'total' => (clone $query)->count(),
            'month_count' => (clone $month)->count(),
            'month_hours' => round((float) (clone $month)->sum('total_hours'), 1),
        ];
    }
}
