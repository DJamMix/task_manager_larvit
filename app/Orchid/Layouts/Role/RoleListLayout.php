<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\Role;

use App\Support\RoleCatalog;
use Orchid\Platform\Models\Role;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Components\Cells\DateTimeSplit;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Layouts\Table;
use Orchid\Screen\TD;

class RoleListLayout extends Table
{
    public $target = 'roles';

    public function columns(): array
    {
        return [
            TD::make('name', 'Название')
                ->sort()
                ->cantHide()
                ->filter(Input::make())
                ->render(fn (Role $role) => Link::make($role->name)
                    ->route('platform.systems.roles.edit', $role->id)),

            TD::make('slug', 'Код')
                ->sort()
                ->cantHide()
                ->filter(Input::make())
                ->render(fn (Role $role) => '<code>' . e($role->slug) . '</code>'),

            TD::make('description', 'Зачем нужна')
                ->render(fn (Role $role) => e(RoleCatalog::description($role->slug) ?: 'Произвольная роль')),

            TD::make('permissions_count', 'Прав')
                ->align(TD::ALIGN_CENTER)
                ->width('80px')
                ->render(function (Role $role) {
                    $count = collect($role->permissions ?? [])->filter()->count();

                    return '<span class="badge text-bg-light border">' . $count . '</span>';
                }),

            TD::make('updated_at', 'Обновлено')
                ->usingComponent(DateTimeSplit::class)
                ->align(TD::ALIGN_RIGHT)
                ->sort(),
        ];
    }
}
