<?php

namespace App\Orchid\Layouts\User;

use App\Models\Project;
use Orchid\Screen\Field;
use Orchid\Screen\Fields\Select;
use Orchid\Screen\Layouts\Rows;

class UserProjectsLayout extends Rows
{
    protected $title;

    protected function fields(): iterable
    {
        return [
            Select::make('user.projects.')
                ->fromModel(Project::class, 'name')
                ->multiple()
                ->title('Доступные проекты')
                ->help('Обязательно для ролей Клиент, Заказчик и Контакт клиента. Сотрудникам проекты даются через «Команда проекта».'),
        ];
    }
}
