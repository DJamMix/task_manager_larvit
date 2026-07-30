<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Mobile;

use App\CoreLayer\Enums\TaskStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\User;
use App\Services\CommentService;
use App\Services\MessageHtmlRenderer;
use App\Services\TaskLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    public function __construct(
        private readonly CommentService $comments,
        private readonly MessageHtmlRenderer $html,
    ) {}

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->assertTasks($user);

        $userId = (int) $user->id;
        $query = Task::query()
            ->with(['project', 'category', 'executor', 'queue'])
            ->where(function ($q) use ($userId) {
                $q->where('executor_id', $userId)
                    ->orWhereRaw('JSON_CONTAINS(COALESCE(observers_ids, "[]"), ?)', [json_encode($userId)]);
            })
            ->whereNotIn('status', [
                TaskStatusEnum::COMPLETED->value,
                TaskStatusEnum::CANCELED->value,
                TaskStatusEnum::UNPAID->value,
                TaskStatusEnum::DEMO->value,
            ])
            ->orderByDesc('id');

        if ($search = trim((string) $request->query('q', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                    ->orWhere('description', 'like', '%' . $search . '%')
                    ->orWhere('id', (int) $search);
            });
        }

        $page = $query->paginate(min(50, max(10, (int) $request->integer('per_page', 20))));

        return response()->json([
            'tasks' => collect($page->items())->map(fn (Task $t) => $this->taskCard($t, $user))->values(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function show(Request $request, Task $task): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->assertTasks($user);
        $this->assertCanView($task, $user);

        $task->load([
            'project',
            'category',
            'executor',
            'creator',
            'queue',
            'attachment',
            'links.relatedTask.queue',
        ]);

        $comments = $task->comments()
            ->with(['user', 'parent.user', 'attachment'])
            ->orderBy('created_at')
            ->get();

        $discussion = $comments->where('is_system', false)->values()
            ->map(fn ($c) => $this->commentCard($c))->values();
        $history = $comments->where('is_system', true)->values()
            ->map(fn ($c) => $this->commentCard($c))->values();

        $canChange = (int) $task->executor_id === (int) $user->id
            && $task->canChangeWorkflow((int) $user->id);

        $transitions = $canChange
            ? TaskStatusEnum::executorTransitions((string) $task->status)
            : [];

        // «Взять в работу» для NEW
        if ($canChange && (string) $task->status === TaskStatusEnum::NEW->value) {
            array_unshift($transitions, [
                'to' => TaskStatusEnum::IN_PROGRESS->value,
                'label' => 'Взять в работу',
                'tone' => 'next',
                'confirm' => null,
            ]);
        }

        return response()->json([
            'task' => $this->taskCard($task, $user, true),
            'comments' => $discussion,
            'history' => $history,
            'status_actions' => $transitions,
            'pipeline' => TaskStatusEnum::pipelineWithState((string) $task->status),
            'can_discuss' => $task->canDiscuss((int) $user->id),
            'can_change_status' => $canChange,
        ]);
    }

    public function comment(Request $request, Task $task): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->assertTasks($user);
        $this->assertCanView($task, $user);

        if (!$task->canDiscuss((int) $user->id)) {
            abort(403, 'Нельзя комментировать');
        }

        if ($request->filled('text') && !$request->filled('comment.text')) {
            $request->merge(['comment' => [
                'text' => $request->input('text'),
                'parent_id' => $request->input('parent_id'),
            ]]);
        }

        $comment = $this->comments->addFromRequest($task, $user, $request);
        $comment->load(['user', 'parent.user', 'attachment']);

        return response()->json(['comment' => $this->commentCard($comment)], 201);
    }

    public function changeStatus(Request $request, Task $task): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->assertTasks($user);
        $this->assertCanView($task, $user);

        if (!$task->canChangeWorkflow((int) $user->id) || (int) $task->executor_id !== (int) $user->id) {
            return response()->json(['message' => 'Нельзя менять статус'], 403);
        }

        $data = $request->validate([
            'status' => ['required', 'string'],
        ]);

        $allowed = collect(TaskStatusEnum::executorTransitions((string) $task->status))
            ->pluck('to')
            ->all();

        if ((string) $task->status === TaskStatusEnum::NEW->value) {
            $allowed[] = TaskStatusEnum::IN_PROGRESS->value;
        }

        $newStatus = $data['status'];
        if (!in_array($newStatus, $allowed, true)) {
            return response()->json(['message' => 'Недопустимый статус'], 422);
        }

        if ($newStatus === TaskStatusEnum::UNPAID->value && $task->type_task == 'bug') {
            $newStatus = TaskStatusEnum::COMPLETED->value;
        }

        $from = (string) $task->status;
        $task->status = $newStatus;
        $task->save();

        app(TaskLogger::class)->logStatusChange($task, $user, $newStatus, null, $from);

        $task->load(['project', 'category', 'executor', 'creator', 'queue', 'attachment', 'links.relatedTask.queue']);

        return response()->json(['task' => $this->taskCard($task, $user, true)]);
    }

    private function taskCard(Task $task, User $viewer, bool $full = false): array
    {
        $status = TaskStatusEnum::tryFrom((string) $task->status);
        $observers = collect($task->observers_ids ?? [])->map(fn ($id) => (int) $id)->all();
        $role = (int) $task->executor_id === (int) $viewer->id
            ? 'executor'
            : (in_array((int) $viewer->id, $observers, true) ? 'observer' : 'other');

        $data = [
            'id' => (int) $task->id,
            'key' => $task->displayKey(),
            'name' => (string) $task->name,
            'status' => (string) $task->status,
            'status_label' => $status?->label() ?? (string) $task->status,
            'status_color' => $status?->color() ?? '#64748b',
            'priority' => $task->priority,
            'project' => $task->project?->name,
            'category' => $task->category?->name,
            'queue' => $task->queue?->name ?? $task->queue?->key,
            'executor' => $task->executor?->name,
            'role' => $role,
            'end_datetime' => $task->end_datetime?->toIso8601String(),
            'end_label' => $task->end_datetime?->format('d.m.Y H:i'),
        ];

        if ($full) {
            $rawDescription = $task->description;
            if (is_array($rawDescription)) {
                $data['description_html'] = $this->html->render($rawDescription, null);
                $data['description'] = strip_tags($data['description_html']);
            } else {
                $html = (string) ($rawDescription ?? '');
                $data['description_html'] = $html !== ''
                    ? $this->html->render($html, strip_tags($html))
                    : '';
                $data['description'] = strip_tags($html);
            }

            $data['estimation_hours'] = $task->estimation_hours;
            $data['hours_spent'] = $task->hours_spent;
            $data['creator'] = $task->creator?->name;
            $data['type_task'] = $task->type_task;
            $data['attachments'] = collect($task->attachment ?? [])->map(fn ($file) => [
                'id' => (int) $file->id,
                'name' => (string) $file->original_name,
                'url' => url('/api/mobile/attachments/' . $file->id),
                'mime' => (string) ($file->mime ?? ''),
            ])->values()->all();
            $data['links'] = collect($task->links ?? [])->map(fn ($link) => [
                'id' => (int) $link->id,
                'type' => (string) ($link->type ?? ''),
                'related_id' => (int) ($link->related_task_id ?? 0),
                'related_key' => $link->relatedTask?->displayKey(),
                'related_name' => $link->relatedTask?->name,
            ])->values()->all();
            $data['observers'] = User::query()
                ->whereIn('id', $observers)
                ->get()
                ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name])
                ->values()
                ->all();
        }

        return $data;
    }

    private function commentCard($comment): array
    {
        $files = [];
        foreach ($comment->attachment ?? [] as $file) {
            $files[] = [
                'id' => (int) $file->id,
                'name' => (string) $file->original_name,
                'url' => url('/api/mobile/attachments/' . $file->id . '?inline=1'),
            ];
        }

        return [
            'id' => (int) $comment->id,
            'text' => (string) ($comment->plain_text ?? ''),
            'html' => (string) ($comment->formatted_text ?? $comment->plain_text ?? ''),
            'system' => (bool) $comment->is_system,
            'author' => [
                'id' => (int) $comment->user_id,
                'name' => $comment->user?->name ?? 'Участник',
                'initials' => $comment->user?->avatarInitials() ?? '?',
                'color' => $comment->user?->avatarColor() ?? '#64748b',
            ],
            'parent_id' => $comment->parent_id ? (int) $comment->parent_id : null,
            'attachments' => $files,
            'created_at' => $comment->created_at?->toIso8601String(),
            'created_label' => $comment->created_at?->format('d.m H:i') ?? '',
        ];
    }

    private function assertTasks(User $user): void
    {
        if (
            !$user->hasAccess('platform.systems.my_tasks')
            && !$user->hasAccess('platform.systems.contact.tasks')
            && !$user->hasAccess('platform.systems.client.project.tasks.view')
        ) {
            abort(403, 'Нет доступа к задачам');
        }
    }

    private function assertCanView(Task $task, User $user): void
    {
        $uid = (int) $user->id;
        $observers = collect($task->observers_ids ?? [])->map(fn ($id) => (int) $id)->all();
        if ((int) $task->executor_id === $uid || in_array($uid, $observers, true)) {
            return;
        }
        if ($user->isClientWithTaskAccess() || $user->isClientAccount()) {
            $projectIds = $user->projects()->pluck('projects.id')->all();
            if (in_array((int) $task->project_id, $projectIds, true)) {
                return;
            }
        }
        abort(403);
    }
}
