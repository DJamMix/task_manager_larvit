<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\User;

use App\Support\RoleCatalog;
use Orchid\Platform\Models\Role;
use Orchid\Screen\Field;
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

        return [
            Label::make('roles_help')
                ->title('Какую роль выбрать')
                ->help($hint),

            Select::make('user.roles.')
                ->fromQuery(Role::query()->orderBy('name'), 'name')
                ->multiple()
                ->title('Роли доступа')
                ->help('Можно несколько. Для клиента / заказчика / контакта обязательно назначьте проекты ниже.'),
        ];
    }
}
