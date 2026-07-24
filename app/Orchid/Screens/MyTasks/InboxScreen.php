<?php

namespace App\Orchid\Screens\MyTasks;

use App\CoreLayer\Enums\TaskPriorityEnum;
use App\CoreLayer\Enums\TaskStatusEnum;
use App\Models\Comment;
use App\Models\Task;
use App\Services\ProjectContext;
use Illuminate\Support\Str;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Screen;
use Orchid\Screen\TD;
use Orchid\Support\Facades\Layout;

class InboxScreen extends Screen
{
    public function query(ProjectContext $context): iterable
    {
        $userId = auth()->id();

        $taskIdsQuery = Task::query()
            ->where(function ($q) use ($userId) {
                $q->where('executor_id', $userId)
                    ->orWhere('creator_id', $userId);
            });
        $context->applyToTaskQuery($taskIdsQuery);
        $taskIds = $taskIdsQuery->pluck('id');

        $comments = collect();
        if ($taskIds->isNotEmpty()) {
            $comments = Comment::with(['user', 'task.project'])
                ->whereIn('task_id', $taskIds)
                ->where('user_id', '!=', $userId)
                ->latest()
                ->limit(50)
                ->get();
        }

        $awaitingEstimation = Task::query()
            ->with(['project', 'category'])
            ->where('executor_id', $userId)
            ->where('status', TaskStatusEnum::ESTIMATION->value);
        $context->applyToTaskQuery($awaitingEstimation);

        $newTasks = Task::query()
            ->with(['project', 'category'])
            ->where('executor_id', $userId)
            ->where('status', TaskStatusEnum::NEW->value);
        $context->applyToTaskQuery($newTasks);

        $newTasksList = $newTasks->orderByDesc('updated_at')->limit(30)->get();
        $estimationList = $awaitingEstimation->orderByDesc('updated_at')->limit(30)->get();

        return [
            'comments' => $comments,
            'awaiting_estimation' => $estimationList,
            'new_tasks' => $newTasksList,
            'inbox_stats' => [
                'new' => $newTasksList->count(),
                'estimation' => $estimationList->count(),
                'comments' => $comments->count(),
            ],
        ];
    }

    public function name(): ?string
    {
        return 'Входящие';
    }

    public function description(): ?string
    {
        return 'Задачи, которые ждут вашего действия, и новые комментарии.';
    }

    public function permission(): ?iterable
    {
        return [
            'platform.systems.my_tasks',
        ];
    }

    public function layout(): iterable
    {
        return [
            Layout::view('partials.project-context-banner'),
            Layout::view('orchid.layouts.inbox-summary'),

            Layout::tabs([
                'Взять в работу' => [
                    Layout::table('new_tasks', $this->taskColumns()),
                ],
                'На оценку' => [
                    Layout::table('awaiting_estimation', $this->taskColumns()),
                ],
                'Комментарии' => [
                    Layout::table('comments', [
                        TD::make('created_at', 'Когда')
                            ->width('140px')
                            ->render(fn (Comment $c) => $c->created_at?->format('d.m.Y H:i') ?? '—'),
                        TD::make('author', 'Автор')
                            ->width('160px')
                            ->render(fn (Comment $c) => e($c->user?->name ?? '—')),
                        TD::make('task', 'Задача')
                            ->render(function (Comment $c) {
                                if (!$c->task_id || !$c->task) {
                                    return '—';
                                }

                                return Link::make($c->task->name)
                                    ->route('platform.systems.my_tasks.view', $c->task_id);
                            }),
                        TD::make('project', 'Проект')
                            ->width('160px')
                            ->render(fn (Comment $c) => e($c->task?->project?->name ?? '—')),
                        TD::make('text', 'Комментарий')
                            ->render(fn (Comment $c) => e(Str::limit((string) $c->plain_text, 120))),
                    ]),
                ],
            ]),
        ];
    }

    private function taskColumns(): array
    {
        return [
            TD::make('name', 'Задача')
                ->render(fn (Task $task) => Link::make($task->name)
                    ->route('platform.systems.my_tasks.view', $task)),
            TD::make('project', 'Проект')
                ->width('180px')
                ->render(fn (Task $task) => e($task->project?->name ?? '—')),
            TD::make('priority', 'Приоритет')
                ->width('160px')
                ->render(function (Task $task) {
                    $priority = TaskPriorityEnum::tryFrom((string) $task->priority);

                    return $priority ? $priority->badgeHtml() : '—';
                }),
            TD::make('category', 'Категория')
                ->width('140px')
                ->render(fn (Task $task) => e($task->category?->name ?? '—')),
            TD::make('updated_at', 'Обновлено')
                ->width('140px')
                ->render(fn (Task $task) => $task->updated_at?->format('d.m.Y H:i') ?? '—'),
            TD::make('action', '')
                ->align(TD::ALIGN_RIGHT)
                ->width('120px')
                ->render(fn (Task $task) => Link::make('Открыть')
                    ->icon('bs.arrow-right')
                    ->class('btn btn-sm btn-outline-primary')
                    ->route('platform.systems.my_tasks.view', $task)),
        ];
    }
}
