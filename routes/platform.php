<?php

declare(strict_types=1);

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
use App\Orchid\Screens\PlatformScreen;
use App\Orchid\Screens\Project\ProjectEditScreen;
use App\Orchid\Screens\Project\ProjectListScreen;
use App\Orchid\Screens\Role\RoleEditScreen;
use App\Orchid\Screens\Role\RoleListScreen;
use App\Orchid\Screens\Task\TaskEditScreen;
use App\Orchid\Screens\Task\TaskListScreen;
use App\Orchid\Screens\TaskCategory\TaskCategoryEditScreen;
use App\Orchid\Screens\TaskCategory\TaskCategoryListScreen;
use App\Orchid\Screens\User\UserEditScreen;
use App\Orchid\Screens\User\UserListScreen;
use App\Orchid\Screens\User\UserProfileScreen;
use Illuminate\Support\Facades\Route;
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
Route::screen('/main', PlatformScreen::class)
    ->name('platform.main');

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
