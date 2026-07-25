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
    $inline = request()->boolean('inline') || str_starts_with($mime, 'audio/') || str_starts_with($mime, 'image/');

    if ($inline) {
        return response()->file($path, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="' . addslashes((string) $attachment->original_name) . '"',
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
    ->name('platform.systems.chats')
    ->breadcrumbs(fn (Trail $trail) => $trail
        ->parent('platform.index')
        ->push('Чаты', route('platform.systems.chats')));

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

Route::get('chats-tasks', function (\Illuminate\Http\Request $request, \App\Services\ChatService $chats) {
    abort_unless($chats->canAccessMessenger($request->user()), 403);

    return response()->json([
        'tasks' => $chats->attachableTasksFor($request->user(), $request->string('q')->toString(), 30),
    ]);
})->name('platform.systems.chats.tasks');

Route::screen('chats/{chat}', \App\Orchid\Screens\Chat\MessengerScreen::class)
    ->name('platform.systems.chats.view')
    ->breadcrumbs(function (Trail $trail, $chat) {
        $model = $chat instanceof \App\Models\Chat
            ? $chat
            : \App\Models\Chat::query()->find($chat);

        $title = $model?->displayTitle() ?? ('Чат #' . (string) $chat);

        return $trail
            ->parent('platform.systems.chats')
            ->push($title, route('platform.systems.chats.view', $chat));
    });
