<?php

namespace App\Orchid\Screens\Contact;

use App\Models\Task;
use App\Orchid\Layouts\Contact\ContactTasksListLayout;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;

class ContactTasksListScreen extends Screen
{
    public function query(): iterable
    {
        $user = auth()->user();
        abort_unless($user?->hasAccess('platform.systems.contact.tasks'), 403);

        $uid = (int) $user->id;
        $projectIds = $user->projects()->pluck('projects.id');

        $tasks = Task::query()
            ->whereIn('project_id', $projectIds)
            ->whereRaw('JSON_CONTAINS(COALESCE(observers_ids, "[]"), ?)', [json_encode($uid)])
            ->with(['project', 'executor', 'category'])
            ->orderByDesc('updated_at')
            ->paginate(20);

        return [
            'tasks' => $tasks,
        ];
    }

    public function name(): ?string
    {
        return 'Наблюдаемые задачи';
    }

    public function description(): ?string
    {
        return 'Задачи, где вы добавлены наблюдателем. Только просмотр и обсуждение.';
    }

    public function permission(): ?iterable
    {
        return ['platform.systems.contact.tasks'];
    }

    public function commandBar(): iterable
    {
        return [];
    }

    public function layout(): iterable
    {
        return [
            Layout::block(ContactTasksListLayout::class)
                ->title('Ваши задачи')
                ->description('Список формируется из задач, где вас указали наблюдателем в подключённых проектах.'),
        ];
    }
}
