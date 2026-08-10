<?php

declare(strict_types=1);

use App\Http\Controllers\ProjectContextController;
use App\Models\TaskAttachment;
use App\Orchid\Layouts\Client\ClientTaskViewLayout;
use App\Orchid\Screens\Acts\ActEditScreen;
use App\Orchid\Screens\Acts\ActListScreen;
use App\Orchid\Screens\Client\ClientListProjectScreen;
use App\Orchid\Screens\Client\ClientListTaskScreen;
use App\Orchid\Screens\Client\ClientViewTaskScreen;
use App\Orchid\Screens\Contact\ContactTasksListScreen;
use App\Orchid\Screens\Contact\ContactTaskViewScreen;
use App\Orchid\Screens\Examples\ExampleActionsScreen;
use App\Orchid\Screens\Examples\ExampleCardsScreen;
use App\Orchid\Screens\Examples\ExampleChartsScreen;
use App\Orchid\Screens\Examples\ExampleFieldsAdvancedScreen;
use App\Orchid\Screens\Examples\ExampleFieldsScreen;
use App\Orchid\Screens\Examples\ExampleGridScreen;
use App\Orchid\Screens\Examples\ExampleLayoutsScreen;
use App\Orchid\Screens\Examples\ExampleScreen;
use App\Orchid\Screens\Examples\ExampleTextEditorsScreen;
use App\Orchid\Screens\MyTasks\MyTasksListScreen;
use App\Orchid\Screens\MyTasks\MyTasksViewScreen;
use App\Orchid\Screens\MyTasks\MyTaskTimeScreen;
use App\Orchid\Screens\MyTasks\InboxScreen;
use App\Orchid\Screens\PlatformScreen;
use App\Orchid\Screens\Project\ProjectEditScreen;
use App\Orchid\Screens\Project\ProjectListScreen;
use App\Orchid\Screens\Role\RoleEditScreen;
use App\Orchid\Screens\Role\RoleListScreen;
use App\Orchid\Screens\System\WelcomeScreen;
use App\Orchid\Screens\Task\TaskEditScreen;
use App\Orchid\Screens\Task\TaskListScreen;
use App\Orchid\Screens\TaskCategory\TaskCategoryEditScreen;
use App\Orchid\Screens\TaskCategory\TaskCategoryListScreen;
use App\Orchid\Screens\TaskScreen;
use App\Orchid\Screens\Telegram\TelegramConnectScreen;
use App\Orchid\Screens\User\UserEditScreen;
use App\Orchid\Screens\User\UserListScreen;
use App\Orchid\Screens\User\UserProfileScreen;
use Illuminate\Support\Facades\Route;
use Orchid\Attachment\Models\Attachment;
use Tabuna\Breadcrumbs\Trail;

/*
|--------------------------------------------------------------------------
| Dashboard Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the need "dashboard" middleware group. Now create something great!
|
*/

// Main
Route::screen('/welcome', WelcomeScreen::class)
    ->name('platform.welcome');

Route::get('project-context/switch', [ProjectContextController::class, 'switch'])
    ->name('platform.project-context.switch');

// Platform > Profile
Route::screen('profile', UserProfileScreen::class)
    ->name('platform.profile')
    ->breadcrumbs(fn (Trail $trail) => $trail
        ->parent('platform.index')
        ->push(__('Profile'), route('platform.profile')));

// Platform > System > Users > User
Route::screen('users/{user}/edit', UserEditScreen::class)
    ->name('platform.systems.users.edit')
    ->breadcrumbs(fn (Trail $trail, $user) => $trail
        ->parent('platform.systems.users')
        ->push($user->name, route('platform.systems.users.edit', $user)));

// Platform > System > Users > Create
Route::screen('users/create', UserEditScreen::class)
    ->name('platform.systems.users.create')
    ->breadcrumbs(fn (Trail $trail) => $trail
        ->parent('platform.systems.users')
        ->push(__('Create'), route('platform.systems.users.create')));

// Platform > System > Users
Route::screen('users', UserListScreen::class)
    ->name('platform.systems.users')
    ->breadcrumbs(fn (Trail $trail) => $trail
        ->parent('platform.index')
        ->push(__('adminpanel.Users'), route('platform.systems.users')));

Route::screen('client/projects/{project}/tasks', ClientListTaskScreen::class)
    ->name('platform.systems.client.project.tasks');

Route::screen('client/projects', ClientListProjectScreen::class)
    ->name('platform.systems.client.projects')
    ->breadcrumbs(fn (Trail $trail) => $trail
        ->parent('platform.index')
        ->push('Мои проекты', route('platform.systems.client.projects')));

Route::screen('client/projects/{project}/tasks/{task}', ClientViewTaskScreen::class)
    ->name('platform.systems.client.project.tasks.view');

Route::screen('contact/tasks', ContactTasksListScreen::class)
    ->name('platform.systems.contact.tasks')
    ->breadcrumbs(fn (Trail $trail) => $trail
        ->parent('platform.index')
        ->push('Наблюдаемые задачи', route('platform.systems.contact.tasks')));

Route::screen('contact/tasks/{task}', ContactTaskViewScreen::class)
    ->name('platform.systems.contact.tasks.view')
    ->breadcrumbs(function (Trail $trail, $task) {
        $model = $task instanceof \App\Models\Task
            ? $task
            : \App\Models\Task::query()->find($task);
        $title = $model?->name ?? ('Задача #' . (string) $task);

        return $trail
            ->parent('platform.systems.contact.tasks')
            ->push($title, route('platform.systems.contact.tasks.view', $task));
    });

Route::screen('telegram/connect', TelegramConnectScreen::class)
    ->name('platform.telegram.connect');


Route::get('task/attachment/download/{attachment}', function (Attachment $attachment) {
    $path = storage_path('app/public/' . $attachment->physicalPath());

    if (!file_exists($path)) {
        abort(404);
    }

    $mime = (string) ($attachment->mime ?: mime_content_type($path) ?: 'application/octet-stream');
    $ext = strtolower((string) ($attachment->extension
        ?: pathinfo((string) $attachment->original_name, PATHINFO_EXTENSION)));
    $isVoiceGroup = ($attachment->group ?? '') === 'voice';

    if ($isVoiceGroup && ($ext === '' || $mime === 'application/octet-stream' || $mime === 'video/webm')) {
        $head = (string) @file_get_contents($path, false, null, 0, 16);
        if (str_starts_with($head, 'RIFF')) {
            $mime = 'audio/wav';
            $ext = 'wav';
        } elseif (str_starts_with($head, 'OggS')) {
            $mime = 'audio/ogg';
            $ext = 'ogg';
        } elseif (strlen($head) >= 4 && $head[0] === "\x1A" && $head[1] === "\x45") {
            $mime = 'audio/webm';
            $ext = 'webm';
        }
    }

    $isVoice = $isVoiceGroup
        || in_array($ext, ['webm', 'ogg', 'oga', 'mp3', 'm4a', 'wav', 'aac', 'opus'], true)
        || str_starts_with($mime, 'audio/')
        || (
            in_array($mime, ['video/webm', 'video/mp4', 'application/octet-stream'], true)
            && in_array($ext, ['webm', 'ogg', 'm4a', 'mp4', 'wav'], true)
        );

    // Голосовые часто приходят как video/webm или octet-stream — без нормализации <audio> молчит
    if ($isVoice) {
        $mime = match (true) {
            str_contains($mime, 'wav') || $ext === 'wav' => 'audio/wav',
            str_contains($mime, 'mpeg') || $ext === 'mp3' => 'audio/mpeg',
            str_contains($mime, 'ogg') || in_array($ext, ['ogg', 'oga', 'opus'], true) => 'audio/ogg',
            str_contains($mime, 'mp4') || in_array($ext, ['m4a', 'mp4', 'aac'], true) => 'audio/mp4',
            str_contains($mime, 'webm') || $ext === 'webm' => 'audio/webm',
            default => (str_starts_with($mime, 'audio/') ? $mime : 'audio/wav'),
        };
    }

    $inline = request()->boolean('inline')
        || $isVoice
        || str_starts_with($mime, 'audio/')
        || str_starts_with($mime, 'image/')
        || $mime === 'video/mp4'
        || $mime === 'video/webm';

    if ($inline) {
        return response()->file($path, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="' . addslashes((string) $attachment->original_name) . '"',
            'Accept-Ranges' => 'bytes',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    return response()->download($path, $attachment->original_name);
})->name('platform.task.attachment.download');


// Platform > Tasks
Route::screen('task', TaskScreen::class)->name('platform.task');

// Platform > System > Tasks
Route::screen('tasks', TaskListScreen::class)
    ->name('platform.systems.tasks')
    ->breadcrumbs(fn (Trail $trail) => $trail
        ->parent('platform.index')
        ->push(__('adminpanel.Tasks'), route('platform.systems.tasks')));

// Platform > System > Tasks > Task
Route::screen('tasks/{task}/edit', TaskEditScreen::class)
    ->name('platform.systems.tasks.edit')
    ->breadcrumbs(fn (Trail $trail, $task) => $trail
        ->parent('platform.systems.tasks')
        ->push($task->name, route('platform.systems.tasks.edit', $task)));

// Platform > System > Tasks > Create
Route::screen('tasks/create', TaskEditScreen::class)
    ->name('platform.systems.tasks.create')
    ->breadcrumbs(fn (Trail $trail) => $trail
        ->parent('platform.systems.tasks')
        ->push(__('project.add'), route('platform.systems.tasks.create')));

// Tracker: boards, sprints, workflow
Route::screen('boards', \App\Orchid\Screens\Tracker\BoardScreen::class)
    ->name('platform.systems.boards')
    ->breadcrumbs(fn (Trail $trail) => $trail
        ->parent('platform.index')
        ->push('Доски', route('platform.systems.boards')));

Route::screen('sprints', \App\Orchid\Screens\Tracker\SprintListScreen::class)
    ->name('platform.systems.sprints')
    ->breadcrumbs(fn (Trail $trail) => $trail
        ->parent('platform.index')
        ->push('Спринты', route('platform.systems.sprints')));

Route::screen('workflow', \App\Orchid\Screens\Tracker\WorkflowDesignerScreen::class)
    ->name('platform.systems.workflow')
    ->breadcrumbs(fn (Trail $trail) => $trail
        ->parent('platform.index')
        ->push('Workflow', route('platform.systems.workflow')));

Route::post('boards/move', function (
    \Illuminate\Http\Request $request,
    \App\Services\WorkflowService $workflows
) {
    if (! $request->user()?->hasAccess('platform.systems.tasks')) {
        abort(403);
    }

    $data = $request->validate([
        'task_id' => 'required|integer|exists:tasks,id',
        'status_id' => 'required|integer|exists:workflow_statuses,id',
        'order' => 'nullable|array',
        'order.*' => 'integer',
    ]);

    $task = \App\Models\Task::query()->findOrFail($data['task_id']);

    try {
        $workflows->changeStatus($task, $request->user(), (int) $data['status_id']);
    } catch (\Illuminate\Validation\ValidationException $e) {
        return response()->json([
            'message' => collect($e->errors())->flatten()->first() ?: 'Переход запрещён',
            'errors' => $e->errors(),
        ], 422);
    }

    if (! empty($data['order'])) {
        foreach ($data['order'] as $i => $id) {
            \App\Models\Task::query()->where('id', $id)->update(['board_sort' => $i]);
        }
    }

    return response()->json(['ok' => true]);
})->name('platform.systems.boards.move');

Route::post('sprints/assign', function (\Illuminate\Http\Request $request) {
    if (! $request->user()?->hasAccess('platform.systems.tasks')) {
        abort(403);
    }

    $data = $request->validate([
        'task_id' => 'required|integer|exists:tasks,id',
        'sprint_id' => 'nullable|integer|exists:sprints,id',
    ]);

    \App\Models\Task::query()->where('id', $data['task_id'])->update([
        'sprint_id' => $data['sprint_id'] ?? null,
    ]);

    return response()->json(['ok' => true]);
})->name('platform.systems.sprints.assign');

Route::post('workflow/save', function (
    \Illuminate\Http\Request $request,
    \App\Services\WorkflowService $workflows
) {
    if (! $request->user()?->hasAccess('platform.systems.tasks')) {
        abort(403);
    }

    $data = $request->validate([
        'statuses' => 'required|array|min:1',
        'statuses.*.id' => 'nullable',
        'statuses.*.name' => 'required|string|max:120',
        'statuses.*.slug' => 'nullable|string|max:64',
        'statuses.*.color' => 'nullable|string|max:16',
        'statuses.*.category' => 'nullable|string|max:32',
        'statuses.*.sort_order' => 'nullable|integer',
        'statuses.*.is_initial' => 'nullable|boolean',
        'statuses.*.is_final' => 'nullable|boolean',
        'statuses.*.is_active' => 'nullable|boolean',
        'transitions' => 'nullable|array',
        'transitions.*.from' => 'required',
        'transitions.*.to' => 'required',
        'transitions.*.name' => 'nullable|string|max:120',
    ]);

    $workflows->saveGraph($data['statuses'], $data['transitions'] ?? []);

    return response()->json([
        'ok' => true,
        'graph' => $workflows->graphPayload(),
    ]);
})->name('platform.systems.workflow.save');

// Platform > System > Acts
Route::screen('acts', ActListScreen::class)
    ->name('platform.systems.acts')
    ->breadcrumbs(fn (Trail $trail) => $trail
        ->parent('platform.index')
        ->push(__('adminpanel.Acts'), route('platform.systems.acts')));

// Platform > System > Acts > Act 
Route::screen('acts/{act}/edit', ActEditScreen::class)
    ->name('platform.systems.acts.edit')
    ->breadcrumbs(fn (Trail $trail, $act) => $trail
        ->parent('platform.systems.acts')
        ->push($act->number, route('platform.systems.acts.edit', $act)));

// Platform > System > Acts > Create
Route::screen('acts/create', ActEditScreen::class)
    ->name('platform.systems.acts.create')
    ->breadcrumbs(fn (Trail $trail) => $trail
        ->parent('platform.systems.acts')
        ->push(__('act.add'), route('platform.systems.acts.create')));

//Platform > System > Acts > Download
Route::get('acts/{act}/download', [ActEditScreen::class, 'downloadWord'])
    ->name('platform.systems.acts.download');

// Platform > System > Roles > Role
Route::screen('roles/{role}/edit', RoleEditScreen::class)
    ->name('platform.systems.roles.edit')
    ->breadcrumbs(fn (Trail $trail, $role) => $trail
        ->parent('platform.systems.roles')
        ->push($role->name, route('platform.systems.roles.edit', $role)));

// Platform > System > Roles > Create
Route::screen('roles/create', RoleEditScreen::class)
    ->name('platform.systems.roles.create')
    ->breadcrumbs(fn (Trail $trail) => $trail
        ->parent('platform.systems.roles')
        ->push(__('Create'), route('platform.systems.roles.create')));

// Platform > System > Roles
Route::screen('roles', RoleListScreen::class)
    ->name('platform.systems.roles')
    ->breadcrumbs(fn (Trail $trail) => $trail
        ->parent('platform.index')
        ->push(__('adminpanel.Roles'), route('platform.systems.roles')));

// Example...
Route::screen('example', ExampleScreen::class)
    ->name('platform.example')
    ->breadcrumbs(fn (Trail $trail) => $trail
        ->parent('platform.index')
        ->push('Example Screen'));

Route::screen('/examples/form/fields', ExampleFieldsScreen::class)->name('platform.example.fields');
Route::screen('/examples/form/advanced', ExampleFieldsAdvancedScreen::class)->name('platform.example.advanced');
Route::screen('/examples/form/editors', ExampleTextEditorsScreen::class)->name('platform.example.editors');
Route::screen('/examples/form/actions', ExampleActionsScreen::class)->name('platform.example.actions');

Route::screen('/examples/layouts', ExampleLayoutsScreen::class)->name('platform.example.layouts');
Route::screen('/examples/grid', ExampleGridScreen::class)->name('platform.example.grid');
Route::screen('/examples/charts', ExampleChartsScreen::class)->name('platform.example.charts');
Route::screen('/examples/cards', ExampleCardsScreen::class)->name('platform.example.cards');

// Route::screen('idea', Idea::class, 'platform.screens.idea');

Route::screen('projects', ProjectListScreen::class)
    ->name('platform.systems.projects')
    ->breadcrumbs(fn (Trail $trail) => $trail
        ->parent('platform.index')
        ->push(__('adminpanel.Projects'), route('platform.systems.projects')));

Route::screen('projects/{project}/edit', ProjectEditScreen::class)
    ->name('platform.systems.projects.edit')
    ->breadcrumbs(fn (Trail $trail, $project) => $trail
        ->parent('platform.systems.projects')
        ->push($project->name, route('platform.systems.projects.edit', $project)));

Route::screen('projects/create', ProjectEditScreen::class)
    ->name('platform.systems.projects.create')
    ->breadcrumbs(fn (Trail $trail) => $trail
        ->parent('platform.systems.projects')
        ->push(__('project.add'), route('platform.systems.projects.create')));


Route::screen('task_categories', TaskCategoryListScreen::class)
    ->name('platform.systems.task_categories')
    ->breadcrumbs(fn (Trail $trail) => $trail
        ->parent('platform.index')
        ->push(__('adminpanel.TaskCategories'), route('platform.systems.task_categories')));

Route::screen('task_categories/{task_category}/edit', TaskCategoryEditScreen::class)
    ->name('platform.systems.task_categories.edit')
    ->breadcrumbs(fn (Trail $trail, $task_category) => $trail
        ->parent('platform.systems.task_categories')
        ->push($task_category->name, route('platform.systems.task_categories.edit', $task_category)));

Route::screen('task_categories/create', TaskCategoryEditScreen::class)
    ->name('platform.systems.task_categories.create')
    ->breadcrumbs(fn (Trail $trail) => $trail
        ->parent('platform.systems.task_categories')
        ->push(__('project.add'), route('platform.systems.task_categories.create')));

Route::screen('task_queues', \App\Orchid\Screens\TaskQueue\TaskQueueListScreen::class)
    ->name('platform.systems.task_queues')
    ->breadcrumbs(fn (Trail $trail) => $trail
        ->parent('platform.index')
        ->push('Очереди задач', route('platform.systems.task_queues')));

Route::screen('task_queues/{queue}/edit', \App\Orchid\Screens\TaskQueue\TaskQueueEditScreen::class)
    ->name('platform.systems.task_queues.edit')
    ->breadcrumbs(fn (Trail $trail, $queue) => $trail
        ->parent('platform.systems.task_queues')
        ->push($queue->key ?? 'Очередь', route('platform.systems.task_queues.edit', $queue)));

Route::screen('task_queues/create', \App\Orchid\Screens\TaskQueue\TaskQueueEditScreen::class)
    ->name('platform.systems.task_queues.create')
    ->breadcrumbs(fn (Trail $trail) => $trail
        ->parent('platform.systems.task_queues')
        ->push('Создать', route('platform.systems.task_queues.create')));

Route::screen('my_tasks', MyTasksListScreen::class)
    ->name('platform.systems.my_tasks')
    ->breadcrumbs(fn (Trail $trail) => $trail
        ->parent('platform.index')
        ->push(__('adminpanel.MyTasks'), route('platform.systems.my_tasks')));

Route::screen('my_tasks/{task}/view', MyTasksViewScreen::class)
    ->name('platform.systems.my_tasks.view')
    ->breadcrumbs(fn (Trail $trail, $task) => $trail
        ->parent('platform.systems.my_tasks')
        ->push($task->name, route('platform.systems.my_tasks.view', $task)));

Route::screen('my_tasks/{task}/time', MyTaskTimeScreen::class)
    ->name('platform.systems.my_tasks.time')
    ->breadcrumbs(fn (Trail $trail, $task) => $trail
        ->parent('platform.systems.my_tasks.view', $task)
        ->push('Учёт времени', route('platform.systems.my_tasks.time', $task)));

Route::screen('inbox', InboxScreen::class)
    ->name('platform.systems.inbox')
    ->breadcrumbs(fn (Trail $trail) => $trail
        ->parent('platform.index')
        ->push('Входящие', route('platform.systems.inbox')));

Route::screen('chats', \App\Orchid\Screens\Chat\MessengerScreen::class)
    ->name('platform.systems.chats');

Route::get('chats-poll', function (\Illuminate\Http\Request $request, \App\Services\ChatService $chats) {
    abort_unless($chats->canAccessMessenger($request->user()), 403);

    return response()->json(
        $chats->pollState(
            $request->user(),
            $request->integer('since') ?: null,
            $request->integer('chat') ?: null
        )
    );
})->name('platform.systems.chats.poll');

Route::post('chats/{chat}/typing', function (
    \Illuminate\Http\Request $request,
    \App\Models\Chat $chat,
    \App\Services\ChatService $chats
) {
    abort_unless($chats->canAccessMessenger($request->user()), 403);
    $chats->markTyping($chat, $request->user());

    return response()->json(['ok' => true]);
})->name('platform.systems.chats.typing');

Route::get('chats-search', function (\Illuminate\Http\Request $request, \App\Services\ChatService $chats) {
    abort_unless($chats->canAccessMessenger($request->user()), 403);

    return response()->json(
        $chats->search($request->user(), $request->string('q')->toString())
    );
})->name('platform.systems.chats.search');

Route::get('chats/{chat}/messages', function (
    \Illuminate\Http\Request $request,
    \App\Models\Chat $chat,
    \App\Services\ChatService $chats
) {
    abort_unless($chats->canAccessMessenger($request->user()), 403);

    $before = $request->integer('before');
    $after = $request->integer('after');
    $limit = $request->integer('limit') ?: 40;

    if ($after > 0) {
        return response()->json(
            $chats->newerPayload($request->user(), $chat, $after, $limit)
        );
    }

    abort_unless($before > 0, 422, 'before or after required');

    return response()->json(
        $chats->historyPayload($request->user(), $chat, $before, $limit)
    );
})->name('platform.systems.chats.messages');

Route::get('tasks-link-search', function (\Illuminate\Http\Request $request) {
    $user = $request->user();
    abort_unless($user, 403);

    $q = trim($request->string('q')->toString());
    $exclude = $request->integer('exclude');
    $projectId = $request->integer('project_id') ?: null;

    $tasks = \App\Models\Task::query()
        ->with(['queue', 'project'])
        ->when($exclude > 0, fn ($query) => $query->where('id', '!=', $exclude))
        ->when($projectId > 0, fn ($query) => $query->where('project_id', $projectId))
        ->when($q !== '', function ($query) use ($q) {
            $query->where(function ($w) use ($q) {
                $w->where('name', 'like', '%' . $q . '%');
                if (ctype_digit($q)) {
                    $w->orWhere('id', (int) $q);
                }
                if (preg_match('/^([A-Za-z][A-Za-z0-9_]*)-?(\d+)$/', $q, $m)) {
                    $w->orWhere(function ($x) use ($m) {
                        $x->where('queue_number', (int) $m[2])
                            ->whereHas('queue', fn ($qq) => $qq->where('key', strtoupper($m[1])));
                    });
                }
            });
        })
        ->orderByDesc('id')
        ->limit(40)
        ->get()
        ->filter(fn (\App\Models\Task $task) => $task->canView((int) $user->id))
        ->values()
        ->map(fn (\App\Models\Task $task) => [
            'id' => $task->id,
            'key' => $task->displayKey(),
            'name' => $task->name,
            'status' => \App\CoreLayer\Enums\TaskStatusEnum::tryFrom((string) $task->status)?->label() ?? (string) $task->status,
            'label' => $task->displayKey() . ' · ' . \Illuminate\Support\Str::limit($task->name, 60),
        ]);

    return response()->json(['tasks' => $tasks]);
})->name('platform.systems.tasks.link-search');

Route::get('chats-tasks', function (\Illuminate\Http\Request $request, \App\Services\ChatService $chats) {
    abort_unless($chats->canAccessMessenger($request->user()), 403);

    return response()->json([
        'tasks' => $chats->attachableTasksFor($request->user(), $request->string('q')->toString(), 30),
    ]);
})->name('platform.systems.chats.tasks');

Route::get('chats-picker', function (\Illuminate\Http\Request $request, \App\Services\ChatService $chats) {
    abort_unless($chats->canAccessMessenger($request->user()), 403);

    return response()->json(['chats' => $chats->chatPickerPayload($request->user())]);
})->name('platform.systems.chats.picker');

Route::post('chats/{chat}/forward', function (
    \Illuminate\Http\Request $request,
    \App\Models\Chat $chat,
    \App\Services\ChatService $chats
) {
    abort_unless($chats->canAccessMessenger($request->user()), 403);
    $data = $request->validate([
        'message_ids' => 'required|array|min:1|max:20',
        'message_ids.*' => 'integer',
        'target_chat_id' => 'required|integer|exists:chats,id',
    ]);
    $target = \App\Models\Chat::query()->findOrFail($data['target_chat_id']);
    $chats->forwardMessages($chat, $target, $request->user(), $data['message_ids']);

    return response()->json(['ok' => true]);
})->name('platform.systems.chats.forward');

Route::post('chats/{chat}/read', function (
    \Illuminate\Http\Request $request,
    \App\Models\Chat $chat,
    \App\Services\ChatService $chats
) {
    abort_unless($chats->canAccessMessenger($request->user()), 403);
    abort_unless($chat->isMember($request->user()->id), 403);

    $data = $request->validate([
        'message_id' => 'nullable|integer',
        'up_to' => 'nullable|integer',
    ]);

    $messageId = (int) ($data['up_to'] ?? $data['message_id'] ?? 0);
    if ($messageId > 0) {
        $chats->markReadUpTo($chat, $request->user(), $messageId);
    } else {
        $chats->markRead($chat, $request->user());
    }

    return response()->json([
        'ok' => true,
        'first_unread_id' => $chats->firstUnreadMessageId($chat->fresh(['members']), $request->user()),
    ]);
})->name('platform.systems.chats.read');

Route::post('chats/{chat}/messages/delete', function (
    \Illuminate\Http\Request $request,
    \App\Models\Chat $chat,
    \App\Services\ChatService $chats
) {
    abort_unless($chats->canAccessMessenger($request->user()), 403);
    $data = $request->validate([
        'message_ids' => 'required|array|min:1|max:20',
        'message_ids.*' => 'integer',
        'scope' => 'required|in:me,everyone',
    ]);

    return response()->json($chats->deleteMessages(
        $chat,
        $request->user(),
        $data['message_ids'],
        $data['scope']
    ));
})->name('platform.systems.chats.messages.delete');

Route::get('chats/{chat}/media', function (
    \Illuminate\Http\Request $request,
    \App\Models\Chat $chat,
    \App\Services\ChatService $chats
) {
    abort_unless($chats->canAccessMessenger($request->user()), 403);

    return response()->json($chats->chatMediaPayload(
        $chat,
        $request->user(),
        $request->string('tab')->toString(),
        $request->integer('page') ?: 1,
        60
    ));
})->name('platform.systems.chats.media');

Route::get('web-push/vapid-key', function (\App\Services\WebPushService $push) {
    return response()->json([
        'public_key' => $push->publicKey(),
        'configured' => $push->isConfigured(),
    ]);
})->name('platform.web-push.vapid-key');

Route::post('web-push/subscribe', function (\Illuminate\Http\Request $request) {
    if (!\Illuminate\Support\Facades\Schema::hasTable('push_subscriptions')) {
        return response()->json([
            'message' => 'Таблица push_subscriptions не создана. На сервере выполните: php artisan migrate',
        ], 503);
    }

    $data = $request->validate([
        'endpoint' => 'required|string',
        'keys.p256dh' => 'required|string',
        'keys.auth' => 'required|string',
    ]);

    try {
        $ua = substr((string) $request->userAgent(), 0, 65535);
        \App\Models\PushSubscription::query()->updateOrCreate(
            ['endpoint' => $data['endpoint']],
            [
                'user_id' => $request->user()->id,
                'public_key' => $data['keys']['p256dh'],
                'auth_token' => $data['keys']['auth'],
                'user_agent' => $ua,
            ]
        );

        // Убираем старые подписки того же браузера — иначе одно сообщение = 2 push
        if ($ua !== '') {
            \App\Models\PushSubscription::query()
                ->where('user_id', $request->user()->id)
                ->where('endpoint', '!=', $data['endpoint'])
                ->where('user_agent', $ua)
                ->delete();
        }
    } catch (\Throwable $e) {
        \Illuminate\Support\Facades\Log::warning('WebPush subscribe failed: '.$e->getMessage());

        return response()->json([
            'message' => 'Не удалось сохранить подписку: '.$e->getMessage(),
        ], 500);
    }

    return response()->json(['ok' => true]);
})->name('platform.web-push.subscribe');

Route::delete('web-push/subscribe', function (\Illuminate\Http\Request $request) {
    $data = $request->validate(['endpoint' => 'required|string']);
    \App\Models\PushSubscription::query()
        ->where('user_id', $request->user()->id)
        ->where('endpoint', $data['endpoint'])
        ->delete();

    return response()->json(['ok' => true]);
})->name('platform.web-push.unsubscribe');

Route::post('web-push/test', function (\Illuminate\Http\Request $request, \App\Services\WebPushService $push) {
    abort_unless($push->isConfigured(), 422, 'Web Push не настроен');

    $count = \App\Models\PushSubscription::query()->where('user_id', $request->user()->id)->count();
    if ($count === 0) {
        return response()->json(['message' => 'Нет подписки. Нажмите «Включить push» ещё раз.'], 422);
    }

    $push->send(
        $request->user(),
        'Проверка push',
        'Если вы видите это — серверные уведомления работают.',
        route('platform.systems.chats')
    );

    return response()->json(['ok' => true, 'subscriptions' => $count]);
})->name('platform.web-push.test');

Route::post('chats/{chat}/calls', function (
    \Illuminate\Http\Request $request,
    \App\Models\Chat $chat,
    \App\Services\ChatService $chats,
    \App\Services\CallService $calls
) {
    abort_unless($chats->canAccessMessenger($request->user()), 403);

    try {
        return response()->json(
            $calls->start($chat, $request->user(), $request->boolean('video', true))
        );
    } catch (\Throwable $e) {
        return response()->json(['message' => $e->getMessage()], 422);
    }
})->name('platform.systems.chats.calls.start');

Route::post('chats/calls/{call}/join', function (
    \Illuminate\Http\Request $request,
    \App\Models\ChatCall $call,
    \App\Services\ChatService $chats,
    \App\Services\CallService $calls
) {
    abort_unless($chats->canAccessMessenger($request->user()), 403);

    try {
        return response()->json($calls->join($call, $request->user()));
    } catch (\Throwable $e) {
        return response()->json(['message' => $e->getMessage()], 422);
    }
})->name('platform.systems.chats.calls.join');

Route::post('chats/calls/{call}/leave', function (
    \Illuminate\Http\Request $request,
    \App\Models\ChatCall $call,
    \App\Services\ChatService $chats,
    \App\Services\CallService $calls
) {
    abort_unless($chats->canAccessMessenger($request->user()), 403);
    $calls->leave($call, $request->user());

    return response()->json(['ok' => true]);
})->name('platform.systems.chats.calls.leave');

Route::post('chats/calls/{call}/decline', function (
    \Illuminate\Http\Request $request,
    \App\Models\ChatCall $call,
    \App\Services\ChatService $chats,
    \App\Services\CallService $calls
) {
    abort_unless($chats->canAccessMessenger($request->user()), 403);
    $calls->decline($call, $request->user());

    return response()->json(['ok' => true]);
})->name('platform.systems.chats.calls.decline');

Route::post('chats/calls/{call}/end', function (
    \Illuminate\Http\Request $request,
    \App\Models\ChatCall $call,
    \App\Services\ChatService $chats,
    \App\Services\CallService $calls
) {
    abort_unless($chats->canAccessMessenger($request->user()), 403);
    $calls->end($call, $request->user());

    return response()->json(['ok' => true]);
})->name('platform.systems.chats.calls.end');

Route::post('chats/calls/{call}/guest-link', function (
    \Illuminate\Http\Request $request,
    \App\Models\ChatCall $call,
    \App\Services\ChatService $chats,
    \App\Services\CallService $calls
) {
    abort_unless($chats->canAccessMessenger($request->user()), 403);

    try {
        return response()->json($calls->enableGuestLink($call, $request->user()));
    } catch (\Throwable $e) {
        return response()->json(['message' => $e->getMessage()], 422);
    }
})->name('platform.systems.chats.calls.guest');

Route::delete('chats/calls/{call}/guest-link', function (
    \Illuminate\Http\Request $request,
    \App\Models\ChatCall $call,
    \App\Services\ChatService $chats,
    \App\Services\CallService $calls
) {
    abort_unless($chats->canAccessMessenger($request->user()), 403);
    $calls->revokeGuestLink($call, $request->user());

    return response()->json(['ok' => true]);
})->name('platform.systems.chats.calls.guest.revoke');

Route::post('chats/{chat}/settings', function (
    \Illuminate\Http\Request $request,
    \App\Models\Chat $chat,
    \App\Services\ChatService $chats
) {
    abort_unless($chats->canAccessMessenger($request->user()), 403);
    $data = $request->validate([
        'title' => 'required|string|max:120',
        'description' => 'nullable|string|max:1000',
    ]);
    $updated = $chats->updateChat($chat, $request->user(), $data);

    return response()->json([
        'ok' => true,
        'title' => $updated->displayTitle($request->user()->id),
        'description' => (string) ($updated->description ?? ''),
        'avatar_url' => $updated->avatarUrl($request->user()->id),
    ]);
})->name('platform.systems.chats.settings');

Route::post('chats/{chat}/avatar', function (
    \Illuminate\Http\Request $request,
    \App\Models\Chat $chat,
    \App\Services\ChatService $chats
) {
    abort_unless($chats->canAccessMessenger($request->user()), 403);
    $request->validate([
        'avatar' => 'required|image|max:5120',
    ]);
    $updated = $chats->uploadChatAvatar($chat, $request->user(), $request->file('avatar'));

    return response()->json([
        'ok' => true,
        'avatar_url' => $updated->avatarUrl($request->user()->id),
        'avatar_initials' => $updated->avatarInitials($request->user()->id),
        'avatar_color' => $updated->avatarColor($request->user()->id),
    ]);
})->name('platform.systems.chats.avatar');

Route::post('chats/{chat}/members/add', function (
    \Illuminate\Http\Request $request,
    \App\Models\Chat $chat,
    \App\Services\ChatService $chats
) {
    abort_unless($chats->canAccessMessenger($request->user()), 403);
    $data = $request->validate([
        'member_ids' => 'required|array|min:1',
        'member_ids.*' => 'integer',
    ]);
    $updated = $chats->addMembers($chat, $request->user(), $data['member_ids']);
    $presence = $chats->presenceMap($updated->members->pluck('id'));

    return response()->json([
        'ok' => true,
        'members' => $updated->members
            ->sortBy(fn ($u) => mb_strtolower($u->displayName()))
            ->values()
            ->map(fn ($u) => [
                'id' => (int) $u->id,
                'name' => $u->displayName(),
                'role' => (string) ($u->pivot->role ?? 'member'),
                'position' => (string) ($u->position ?? ''),
                'is_owner' => ($u->pivot->role ?? '') === 'owner',
                'online' => !empty($presence[(int) $u->id]),
                'avatar_url' => (string) $u->avatarUrl(),
                'avatar_initials' => (string) $u->avatarInitials(),
                'avatar_color' => (string) $u->avatarColor(),
            ]),
        'count' => $updated->members->count(),
    ]);
})->name('platform.systems.chats.members.add');

Route::delete('chats/{chat}/members/{user}', function (
    \Illuminate\Http\Request $request,
    \App\Models\Chat $chat,
    \App\Models\User $user,
    \App\Services\ChatService $chats
) {
    abort_unless($chats->canAccessMessenger($request->user()), 403);
    $updated = $chats->removeMember($chat, $request->user(), (int) $user->id);

    return response()->json([
        'ok' => true,
        'removed_id' => (int) $user->id,
        'count' => $updated->members->count(),
    ]);
})->name('platform.systems.chats.members.remove');

Route::delete('chats/{chat}', function (
    \Illuminate\Http\Request $request,
    \App\Models\Chat $chat,
    \App\Services\ChatService $chats
) {
    abort_unless($chats->canAccessMessenger($request->user()), 403);
    $chats->deleteGroup($chat, $request->user());

    return response()->json([
        'ok' => true,
        'redirect' => route('platform.systems.chats'),
    ]);
})->name('platform.systems.chats.destroy');

Route::post('chats/{chat}/pin', function (
    \Illuminate\Http\Request $request,
    \App\Models\Chat $chat,
    \App\Services\ChatService $chats
) {
    abort_unless($chats->canAccessMessenger($request->user()), 403);
    $pinned = $chats->togglePin($chat, $request->user());

    return response()->json(['ok' => true, 'pinned' => $pinned]);
})->name('platform.systems.chats.pin');

Route::post('chats/{chat}/mute', function (
    \Illuminate\Http\Request $request,
    \App\Models\Chat $chat,
    \App\Services\ChatService $chats
) {
    abort_unless($chats->canAccessMessenger($request->user()), 403);
    $muted = $chats->toggleMute($chat, $request->user());

    return response()->json(['ok' => true, 'muted' => $muted]);
})->name('platform.systems.chats.mute');

Route::screen('chats/{chat}', \App\Orchid\Screens\Chat\MessengerScreen::class)
    ->name('platform.systems.chats.view');
