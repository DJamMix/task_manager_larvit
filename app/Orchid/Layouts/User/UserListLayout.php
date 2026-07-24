<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\User;

use App\Models\User;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Actions\DropDown;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Actions\ModalToggle;
use Orchid\Screen\Components\Cells\DateTimeSplit;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Layouts\Persona;
use Orchid\Screen\Layouts\Table;
use Orchid\Screen\TD;

class UserListLayout extends Table
{
    public $target = 'users';

    public function columns(): array
    {
        return [
            TD::make('name', 'Имя')
                ->sort()
                ->cantHide()
                ->filter(Input::make())
                ->render(fn (User $user) => new Persona($user->presenter())),

            TD::make('position', 'Должность')
                ->sort()
                ->filter(Input::make())
                ->width('140px')
                ->render(fn (User $user) => $user->position
                    ? '<span class="badge text-bg-light border">' . e($user->position) . '</span>'
                    : '<span class="text-muted">—</span>'),

            TD::make('roles', 'Роль')
                ->cantHide()
                ->width('180px')
                ->render(function (User $user) {
                    $roles = $user->roles->pluck('name')->filter();
                    if ($roles->isEmpty()) {
                        return '<span class="text-muted">Без роли</span>';
                    }

                    return $roles
                        ->map(fn ($name) => '<span class="badge text-bg-secondary me-1">' . e($name) . '</span>')
                        ->implode(' ');
                }),

            TD::make('email', 'Электронная почта')
                ->sort()
                ->cantHide()
                ->filter(Input::make())
                ->render(fn (User $user) => ModalToggle::make($user->email)
                    ->modal('editUserModal')
                    ->modalTitle($user->presenter()->title())
                    ->method('saveUser')
                    ->asyncParameters([
                        'user' => $user->id,
                    ])),

            TD::make('updated_at', 'Последнее редактирование')
                ->usingComponent(DateTimeSplit::class)
                ->align(TD::ALIGN_RIGHT)
                ->sort(),

            TD::make('Действия')
                ->align(TD::ALIGN_CENTER)
                ->width('100px')
                ->render(fn (User $user) => DropDown::make()
                    ->icon('bs.three-dots-vertical')
                    ->list([
                        Link::make('Изменить')
                            ->route('platform.systems.users.edit', $user->id)
                            ->icon('bs.pencil'),

                        Button::make('Удалить')
                            ->icon('bs.trash3')
                            ->confirm('Удалить пользователя? Это действие необратимо.')
                            ->method('remove', [
                                'id' => $user->id,
                            ]),
                    ])),
        ];
    }
}
