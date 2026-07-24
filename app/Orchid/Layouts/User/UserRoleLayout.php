<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\User;

use App\Models\Project;
use App\Support\RoleCatalog;
use Orchid\Platform\Models\Role;
use Orchid\Screen\Fields\Label;
use Orchid\Screen\Fields\Select;
use Orchid\Screen\Layouts\Rows;

class UserRoleLayout extends Rows
{
    public function fields(): array
    {
        $hint = collect(RoleCatalog::definitions())
            ->map(fn (array $def, string $slug) => "<b>{$def['name']}</b> <code>{$slug}</code> — {$def['description']}")
            ->implode('<br>');

        $hasProjects = Project::query()->exists();

        return [
            Label::make('roles_help')
                ->title('Какую роль выбрать')
                ->help($hint),

            Select::make('user.roles.')
                ->fromQuery(Role::query()->orderBy('name'), 'name')
                ->multiple()
                ->title('Роли доступа')
                ->help('Можно несколько. Для клиента / заказчика / контакта обязательно выберите проекты в поле ниже.'),

            // fromModel обязателен: иначе в value попадает Eloquent Collection и Select падает
            // («Collection could not be converted to int»).
            Select::make('user.projects.')
                ->fromModel(Project::class, 'name')
                ->multiple()
                ->title('Проекты клиента')
                ->help($hasProjects
                    ? 'Обязательно для Клиент, Заказчик и Контакт клиента. Сотрудникам проекты назначаются в карточке проекта (команда).'
                    : 'Сначала создайте хотя бы один проект в разделе «Проекты».')
                ->empty($hasProjects ? 'Выберите проект(ы)' : 'Нет проектов — создайте проект'),
        ];
    }
}
