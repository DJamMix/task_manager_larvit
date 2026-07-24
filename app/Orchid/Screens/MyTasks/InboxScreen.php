<?php

namespace App\Orchid\Screens\MyTasks;

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
            ->where('executor_id', $userId)
            ->where('status', 'estimation');
        $context->applyToTaskQuery($awaitingEstimation);

        $newTasks = Task::query()
            ->where('executor_id', $userId)
            ->where('status', 'new');
        $context->applyToTaskQuery($newTasks);

        return [
            'comments' => $comments,
            'awaiting_estimation' => $awaitingEstimation->with('project')->orderByDesc('updated_at')->limit(10)->get(),
            'new_tasks' => $newTasks->with('project')->orderByDesc('updated_at')->limit(10)->get(),
        ];
    }

    public function name(): ?string
    {
        return 'Входящие';
    }

    public function description(): ?string
    {
        return 'Новые назначения, задачи на оценку и свежие комментарии по вашим задачам.';
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
            Layout::tabs([
                'Требуют внимания' => Layout::view('orchid.layouts.inbox-attention'),
                'Комментарии' => Layout::table('comments', [
                    TD::make('created_at', 'Когда')
                        ->render(fn (Comment $c) => $c->created_at?->format('d.m.Y H:i') ?? '—'),
                    TD::make('author', 'Автор')
                        ->render(fn (Comment $c) => $c->user?->name ?? '—'),
                    TD::make('task', 'Задача')
                        ->render(function (Comment $c) {
                            if (!$c->task_id || !$c->task) {
                                return '—';
                            }

                            return Link::make($c->task->name)
                                ->route('platform.systems.my_tasks.view', $c->task_id);
                        }),
                    TD::make('text', 'Текст')
                        ->render(fn (Comment $c) => e(Str::limit((string) $c->plain_text, 100))),
                ]),
            ]),
        ];
    }
}
