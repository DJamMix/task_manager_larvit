<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\Role;

use Orchid\Screen\Field;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\Label;
use Orchid\Screen\Layouts\Rows;

class RoleEditLayout extends Rows
{
    public function fields(): array
    {
        return [
            Label::make('role_hint')
                ->title('Подсказка')
                ->help('Рекомендуемые роли: <b>admin</b>, <b>pm</b>, <b>employee</b>, <b>client</b>, <b>client_employer</b>. Не удаляйте существующие роли с прода — лучше отредактируйте права.'),

            Input::make('role.name')
                ->type('text')
                ->max(255)
                ->required()
                ->title('Название')
                ->placeholder('Проектный менеджер')
                ->help('Как роль видна в списке пользователей'),

            Input::make('role.slug')
                ->type('text')
                ->max(255)
                ->required()
                ->title('Код (slug)')
                ->placeholder('pm')
                ->help('Технический код. Не меняйте у существующих ролей на проде без необходимости.'),
        ];
    }
}
