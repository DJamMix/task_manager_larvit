<?php

declare(strict_types=1);

namespace App\Orchid;

use Orchid\Platform\Dashboard;
use Orchid\Platform\ItemPermission;
use Orchid\Platform\OrchidServiceProvider;
use Orchid\Screen\Actions\Menu;

class PlatformProvider extends OrchidServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @param Dashboard $dashboard
     *
     * @return void
     */
    public function boot(Dashboard $dashboard): void
    {
        parent::boot($dashboard);

        // ...
    }

    /**
     * Register the application menu.
     *
     * @return Menu[]
     */
    public function menu(): array
    {
        return [
            Menu::make(__('adminpanel.MyTasks'))
                ->icon('bs.journal-bookmark')
                ->route('platform.systems.my_tasks')
                ->permission('platform.systems.my_tasks')
                ->title('Инструменты сотрудника'),

            Menu::make('Входящие')
                ->icon('bs.inbox')
                ->route('platform.systems.inbox')
                ->permission('platform.systems.my_tasks')
                ->badge(fn () => $this->inboxBadgeCount()),

            Menu::make('Моё время')
                ->icon('bs.clock-history')
                ->route('platform.systems.my_time')
                ->permission('platform.systems.my_tasks')
                ->divider(),

            Menu::make('Мои проекты')
                ->icon('bs.briefcase-fill')
                ->route('platform.systems.client.projects')
                ->permission('platform.systems.client.projects')
                ->title('Инструменты клиента')
                ->divider(),

            Menu::make(__('adminpanel.Users'))
                ->icon('bs.people')
                ->route('platform.systems.users')
                ->permission('platform.systems.users')
                ->title(__('adminpanel.access_controls')),

            Menu::make(__('adminpanel.Roles'))
                ->icon('bs.shield')
                ->route('platform.systems.roles')
                ->permission('platform.systems.roles'),

            Menu::make(__('adminpanel.Acts'))
                ->icon('bs.journal-text')
                ->route('platform.systems.acts')
                ->permission('platform.systems.acts'),

            Menu::make(__('adminpanel.Tasks'))
                ->icon('bs.card-checklist')
                ->route('platform.systems.tasks')
                ->permission('platform.systems.tasks'),

            Menu::make(__('adminpanel.Projects'))
                ->icon('bs.file-earmark-text')
                ->route('platform.systems.projects')
                ->permission('platform.systems.projects'),

            Menu::make(__('adminpanel.TaskCategories'))
                ->icon('bs.bookmarks')
                ->route('platform.systems.task_categories')
                ->permission('platform.systems.task_categories')
                ->divider(),
        ];
    }

    private function inboxBadgeCount(): ?int
    {
        if (!auth()->check() || !auth()->user()->hasAccess('platform.systems.my_tasks')) {
            return null;
        }

        $userId = auth()->id();
        $count = \App\Models\Task::where('executor_id', $userId)
            ->whereIn('status', ['new', 'estimation'])
            ->count();

        return $count > 0 ? $count : null;
    }

    /**
     * Register permissions for the application.
     *
     * @return ItemPermission[]
     */
    public function permissions(): array
    {
        return [
            ItemPermission::group(__('System'))
                ->addPermission('platform.systems.roles', __('adminpanel.Roles'))
                ->addPermission('platform.systems.users', __('adminpanel.Users'))
                ->addPermission('platform.systems.attachment', 'Загрузка файлов')
                ->addPermission('platform.systems.tasks', __('adminpanel.Tasks'))
                ->addPermission('platform.systems.projects', __('adminpanel.Projects'))
                ->addPermission('platform.systems.task_categories', __('adminpanel.TaskCategories')),

            ItemPermission::group('Сотрудник')
                ->addPermission('platform.systems.my_tasks', __('adminpanel.MyTasks')),

            ItemPermission::group('Клиент')
                ->addPermission('platform.systems.client.project.tasks', 'Просмотр списка задач')
                ->addPermission('platform.systems.client.projects', 'Проекты клиента')
                ->addPermission('platform.systems.client.project.tasks.view', 'Просмотр задач'),
            
            ItemPermission::group('Менеджер')
                ->addPermission('platform.systems.acts', __('adminpanel.Acts'))
        ];
    }
}
