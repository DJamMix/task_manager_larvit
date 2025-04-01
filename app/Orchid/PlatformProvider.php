<?php

declare(strict_types=1);

namespace App\Orchid;

use Orchid\Platform\Dashboard;
use Orchid\Platform\ItemPermission;
use Orchid\Platform\OrchidServiceProvider;
use Orchid\Screen\Actions\Menu;
use Orchid\Support\Color;

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
            Menu::make('О сервисе')
                ->icon('bs.book')
                ->title('Основная информация')
                ->route(config('platform.index')),

            Menu::make(__('adminpanel.MyTasks'))
                ->icon('bs.journal-bookmark')
                ->route('platform.systems.my_tasks')
                ->permission('platform.systems.my_tasks')
                ->divider(),

            // Menu::make('Sample Screen')
            //     ->icon('bs.collection')
            //     ->route('platform.example')
            //     ->badge(fn () => 6),

            // Menu::make('Form Elements')
            //     ->icon('bs.card-list')
            //     ->route('platform.example.fields')
            //     ->active('*/examples/form/*'),

            // Menu::make('Layouts Overview')
            //     ->icon('bs.window-sidebar')
            //     ->route('platform.example.layouts'),

            // Menu::make('Grid System')
            //     ->icon('bs.columns-gap')
            //     ->route('platform.example.grid'),

            // Menu::make('Charts')
            //     ->icon('bs.bar-chart')
            //     ->route('platform.example.charts'),

            // Menu::make('Cards')
            //     ->icon('bs.card-text')
            //     ->route('platform.example.cards')
            //     ->divider(),

            Menu::make(__('adminpanel.Users'))
                ->icon('bs.people')
                ->route('platform.systems.users')
                ->permission('platform.systems.users')
                ->title(__('adminpanel.access_controls')),

            Menu::make(__('adminpanel.Roles'))
                ->icon('bs.shield')
                ->route('platform.systems.roles')
                ->permission('platform.systems.roles'),

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

            // Menu::make('Documentation')
            //     ->title('Docs')
            //     ->icon('bs.box-arrow-up-right')
            //     ->url('https://orchid.software/en/docs')
            //     ->target('_blank'),

            // Menu::make('Changelog')
            //     ->icon('bs.box-arrow-up-right')
            //     ->url('https://github.com/orchidsoftware/platform/blob/master/CHANGELOG.md')
            //     ->target('_blank')
            //     ->badge(fn () => Dashboard::version(), Color::DARK),
        ];
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
                ->addPermission('platform.systems.tasks', __('adminpanel.Tasks'))
                ->addPermission('platform.systems.my_tasks', __('adminpanel.MyTasks'))
                ->addPermission('platform.systems.projects', __('adminpanel.Projects'))
                ->addPermission('platform.systems.task_categories', __('adminpanel.TaskCategories')),
        ];
    }
}
