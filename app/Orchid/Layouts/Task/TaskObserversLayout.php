<?php

namespace App\Orchid\Layouts\Task;

use App\Models\Task;
use App\Models\User;
use App\Support\RoleCatalog;
use Orchid\Screen\Fields\Select;
use Orchid\Screen\Layouts\Rows;

class TaskObserversLayout extends Rows
{
    protected $title;

    protected function fields(): iterable
    {
        $task = $this->query->get('task');
        $projectId = $task instanceof Task ? $task->project_id : null;

        $options = User::query()
            ->where(function ($q) use ($projectId) {
                $q->whereHas('roles', fn ($r) => $r->whereIn('slug', RoleCatalog::STAFF_SLUGS));

                if ($projectId) {
                    $q->orWhere(function ($qq) use ($projectId) {
                        $qq->whereHas('roles', fn ($r) => $r->whereIn('slug', RoleCatalog::CLIENT_SLUGS))
                            ->whereHas('projects', fn ($p) => $p->where('projects.id', $projectId));
                    });
                }
            })
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (User $u) => [
                $u->id => $u->displayName() . ($u->isClientContact() ? ' · контакт' : ''),
            ])
            ->all();

        return [
            Select::make('task.observers_ids.')
                ->options($options)
                ->multiple()
                ->title('Наблюдатели')
                ->help('Сотрудники и клиентские контакты проекта. Контакт видит только задачи, где он наблюдатель.')
                ->empty('Без наблюдателей'),
        ];
    }
}
