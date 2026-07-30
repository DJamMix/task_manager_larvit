<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\User;

use App\Support\RoleCatalog;
use Orchid\Screen\Field;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\Picture;
use Orchid\Screen\Fields\Select;
use Orchid\Screen\Layouts\Rows;

class UserEditLayout extends Rows
{
    public function fields(): array
    {
        return [
            Picture::make('user.avatar_path')
                ->title('Аватар')
                ->storage('public')
                ->path('avatars')
                ->targetRelativeUrl()
                ->acceptedFiles('image/jpeg,image/png,image/webp,image/gif')
                ->help('Своя фотография. Если не загружена — инициалы или Gravatar.'),

            Input::make('user.name')
                ->type('text')
                ->max(255)
                ->required()
                ->title('Имя')
                ->placeholder('Например: Влад')
                ->help('Только имя. Должность указывается отдельно — не пишите «Влад Бэкенд» в имени.'),

            Select::make('user.position')
                ->options(RoleCatalog::positionOptions())
                ->empty('Не указана')
                ->title('Должность')
                ->help('Backend, Frontend, Designer и т.д. — видно рядом с именем в списках и комментариях')
                ->allowAdd()
                ->canSee(auth()->user()?->hasAccess('platform.systems.users') ?? false),

            Input::make('user.email')
                ->type('email')
                ->required()
                ->title('Электронная почта')
                ->placeholder('email@example.com'),
        ];
    }
}
