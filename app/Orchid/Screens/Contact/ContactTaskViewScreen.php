<?php

namespace App\Orchid\Screens\Contact;

use App\CoreLayer\Enums\TaskStatusEnum;
use App\Models\Task;
use App\Orchid\Layouts\Comment\DiscussionComposerLayout;
use App\Services\CommentService;
use Illuminate\Http\Request;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;

class ContactTaskViewScreen extends Screen
{
    public $task;

    public function query(Task $task, Request $request): iterable
    {
        $user = $request->user();
        abort_unless($user?->hasAccess('platform.systems.contact.tasks'), 403);
        abort_unless($task->canView((int) $user->id), 403, 'Нет доступа к этой задаче');

        $task->load(['project', 'executor', 'creator', 'category', 'attachment']);

        $comments = $task->comments()
            ->with(['user', 'parent.user', 'attachment'])
            ->orderBy('created_at')
            ->get();

        return [
            'task' => $task,
            'task_status_label' => TaskStatusEnum::tryFrom($task->status)?->label(),
            'discussion_comments' => $comments,
            'notify_options' => $task->participantsForNotify(),
            'can_discuss' => $task->canDiscuss((int) $user->id),
            'is_observer_only' => true,
            'viewer_role' => 'contact',
            'show_time_link' => false,
            'time_route' => null,
            'status_pipeline' => TaskStatusEnum::pipelineWithState((string) $task->status),
            'status_actions' => [],
            'status_hint' => 'Вы наблюдатель: можно следить за статусом и писать в обсуждении.',
            'user' => $user,
        ];
    }

    public function name(): ?string
    {
        return $this->task->name ?? 'Задача';
    }

    public function description(): ?string
    {
        return 'Просмотр задачи и обсуждение';
    }

    public function permission(): ?iterable
    {
        return ['platform.systems.contact.tasks'];
    }

    public function commandBar(): iterable
    {
        return [
            Link::make('К списку')
                ->icon('bs.arrow-left')
                ->route('platform.systems.contact.tasks'),
        ];
    }

    public function layout(): iterable
    {
        $layouts = [
            Layout::view('orchid.layouts.task-workspace'),
            Layout::view('orchid.layouts.composer-anchor'),
        ];

        if ($this->task && $this->task->canDiscuss()) {
            $layouts[] = Layout::wrapper('orchid.layouts.composer-shell', [
                'composer' => DiscussionComposerLayout::class,
            ]);
        }

        return $layouts;
    }

    public function addComment(Request $request, Task $task, CommentService $comments)
    {
        abort_unless($request->user()?->hasAccess('platform.systems.contact.tasks'), 403);
        abort_unless($task->canDiscuss((int) $request->user()->id), 403);

        $comments->addFromRequest($task, $request->user(), $request);
        Toast::success('Сообщение отправлено');

        return redirect()->route('platform.systems.contact.tasks.view', $task);
    }
}
