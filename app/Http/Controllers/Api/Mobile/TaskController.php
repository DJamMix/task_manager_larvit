<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Mobile;

use App\CoreLayer\Enums\TaskStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\User;
use App\Services\CommentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TaskController extends Controller
{
    public function __construct(
        private readonly CommentService $comments,
    ) {}

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->assertTasks($user);

        $userId = (int) $user->id;
        $query = Task::query()
            ->with(['project', 'category', 'executor'])
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

        $task->load(['project', 'category', 'executor', 'creator']);
        $comments = $task->comments()
            ->with(['user', 'parent.user', 'attachment'])
            ->orderBy('created_at')
            ->get()
            ->map(fn ($c) => $this->commentCard($c))
            ->values();

        return response()->json([
            'task' => $this->taskCard($task, $user, true),
            'comments' => $comments,
        ]);
    }

    public function comment(Request $request, Task $task): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->assertTasks($user);
        $this->assertCanView($task, $user);

        // Accept simple { text: "..." } from mobile
        if ($request->filled('text') && !$request->filled('comment.text')) {
            $request->merge(['comment' => ['text' => $request->input('text')]]);
        }

        $comment = $this->comments->addFromRequest($task, $user, $request);
        $comment->load(['user', 'parent.user', 'attachment']);

        return response()->json(['comment' => $this->commentCard($comment)], 201);
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
            'name' => (string) $task->name,
            'status' => (string) $task->status,
            'status_label' => $status?->label() ?? (string) $task->status,
            'status_color' => $status?->color() ?? '#64748b',
            'priority' => $task->priority,
            'project' => $task->project?->name,
            'category' => $task->category?->name,
            'executor' => $task->executor?->name,
            'role' => $role,
            'end_datetime' => $task->end_datetime?->toIso8601String(),
        ];

        if ($full) {
            $data['description'] = is_array($task->description)
                ? Str::limit(strip_tags(json_encode($task->description, JSON_UNESCAPED_UNICODE)), 2000)
                : (string) ($task->description ?? '');
            $data['estimation_hours'] = $task->estimation_hours;
            $data['hours_spent'] = $task->hours_spent;
            $data['creator'] = $task->creator?->name;
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
        // Clients with project access
        if ($user->isClientWithTaskAccess() || $user->isClientAccount()) {
            $projectIds = $user->projects()->pluck('projects.id')->all();
            if (in_array((int) $task->project_id, $projectIds, true)) {
                return;
            }
        }
        abort(403);
    }
}
