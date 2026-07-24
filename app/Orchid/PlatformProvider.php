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

            Menu::make('Чаты')
                ->icon('bs.chat-dots')
                ->route('platform.systems.chats')
                ->permission('platform.systems.chats')
                ->badge(fn () => $this->chatsBadgeCount())
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

    private function chatsBadgeCount(): ?int
    {
        if (!auth()->check() || !auth()->user()->hasAccess('platform.systems.chats')) {
            return null;
        }

        try {
            $userId = auth()->id();

            return \App\Models\ChatMessage::query()
                ->whereIn('chat_id', function ($q) use ($userId) {
                    $q->select('chat_id')->from('chat_user')->where('user_id', $userId);
                })
                ->where('user_id', '!=', $userId)
                ->whereRaw('created_at > COALESCE(
                    (SELECT last_read_at FROM chat_user WHERE chat_user.chat_id = chat_messages.chat_id AND chat_user.user_id = ?),
                    "1970-01-01"
                )', [$userId])
                ->count() ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Register permissions for the application.
     *
     * @return ItemPermission[]
     */
    public function permissions(): array
    {
        return [
            ItemPermission::group('Система')
                ->addPermission('platform.systems.roles', 'Роли')
                ->addPermission('platform.systems.users', 'Пользователи')
                ->addPermission('platform.systems.attachment', 'Загрузка файлов'),

            ItemPermission::group('Управление работой')
                ->addPermission('platform.systems.tasks', 'Все задачи')
                ->addPermission('platform.systems.projects', 'Проекты')
                ->addPermission('platform.systems.task_categories', 'Категории задач')
                ->addPermission('platform.systems.acts', 'Акты'),

            ItemPermission::group('Сотрудник')
                ->addPermission('platform.systems.my_tasks', 'Мои задачи / входящие / время')
                ->addPermission('platform.systems.chats', 'Чаты (участие)')
                ->addPermission('platform.systems.chats.create', 'Чаты (создание)'),

            ItemPermission::group('Клиент / Заказчик')
                ->addPermission('platform.systems.client.projects', 'Мои проекты')
                ->addPermission('platform.systems.client.project.tasks', 'Список задач проекта')
                ->addPermission('platform.systems.client.project.tasks.view', 'Карточка задачи'),
        ];
    }
}
