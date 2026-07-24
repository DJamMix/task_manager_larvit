<?php

declare(strict_types=1);

namespace App\Orchid\Screens\User;

use App\Orchid\Layouts\Role\RolePermissionLayout;
use App\Orchid\Layouts\User\UserEditLayout;
use App\Orchid\Layouts\User\UserPasswordLayout;
use App\Orchid\Layouts\User\UserProjectsLayout;
use App\Orchid\Layouts\User\UserRoleLayout;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Orchid\Access\Impersonation;
use App\Models\User;
use Orchid\Screen\Action;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Screen;
use Orchid\Support\Color;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;

class UserEditScreen extends Screen
{
    /**
     * @var User
     */
    public $user;

    /**
     * Fetch data to be displayed on the screen.
     *
     * @return array
     */
    public function query(User $user): iterable
    {
        $user->load(['roles', 'projects']);

        return [
            'user'       => $user,
            'permission' => $user->getStatusPermission(),
        ];
    }

    /**
     * The name of the screen displayed in the header.
     */
    public function name(): ?string
    {
        return $this->user->exists ? 'Редактирование пользователя' : 'Новый пользователь';
    }

    public function description(): ?string
    {
        return 'Имя, должность, роли доступа и проекты клиента. Должность не пишите в имени.';
    }

    public function permission(): ?iterable
    {
        return [
            'platform.systems.users',
        ];
    }

    /**
     * The screen's action buttons.
     *
     * @return Action[]
     */
    public function commandBar(): iterable
    {
        return [
            Button::make(__('Impersonate user'))
                ->icon('bg.box-arrow-in-right')
                ->confirm(__('You can revert to your original state by logging out.'))
                ->method('loginAs')
                ->canSee($this->user->exists && $this->user->id !== \request()->user()->id),

            Button::make(__('project.remove.title'))
                ->icon('bs.trash3')
                ->confirm(__('project.remove.warning'))
                ->method('remove')
                ->canSee($this->user->exists),

            Button::make(__('Save'))
                ->icon('bs.check-circle')
                ->method('save'),
        ];
    }

    /**
     * @return \Orchid\Screen\Layout[]
     */
    public function layout(): iterable
    {
        $layouts = [

            Layout::block(UserEditLayout::class)
                ->title('Профиль')
                ->description('Имя отдельно, должность отдельно — так в списках и комментариях сразу видно, кто это.')
                ->commands(
                    Button::make(__('Save'))
                        ->type(Color::BASIC)
                        ->icon('bs.check-circle')
                        ->canSee($this->user->exists)
                        ->method('save')
                ),

            Layout::block(UserPasswordLayout::class)
                ->title('Пароль')
                ->description('Оставьте пустым, если не нужно менять.')
                ->commands(
                    Button::make(__('Save'))
                        ->type(Color::BASIC)
                        ->icon('bs.check-circle')
                        ->canSee($this->user->exists)
                        ->method('save')
                ),

            Layout::block(UserRoleLayout::class)
                ->title('Роли доступа')
                ->description('Роль = набор прав. Должность (Backend/Frontend) — это не роль.')
                ->commands(
                    Button::make(__('Save'))
                        ->type(Color::BASIC)
                        ->icon('bs.check-circle')
                        ->canSee($this->user->exists)
                        ->method('save')
                ),

            Layout::block(RolePermissionLayout::class)
                ->title('Доп. права')
                ->description('Точечные права поверх ролей — только если нужно исключение.')
                ->commands(
                    Button::make(__('Save'))
                        ->type(Color::BASIC)
                        ->icon('bs.check-circle')
                        ->canSee($this->user->exists)
                        ->method('save')
                ),

        ];

        if ($this->user->exists) {
            $layouts[] = Layout::block(UserProjectsLayout::class)
                ->title('Проекты клиента / заказчика')
                ->description('Нужны только для ролей Клиент и Заказчик. Для сотрудников проекты назначаются в карточке проекта (команда).')
                ->commands(
                    Button::make(__('Save'))
                        ->type(Color::BASIC)
                        ->icon('bs.check-circle')
                        ->method('save')
                );
        }

        return $layouts;
    }

    /**
     * @return \Illuminate\Http\RedirectResponse
     */
    public function save(User $user, Request $request)
    {
        $request->validate([
            'user.name' => 'required|string|max:255',
            'user.position' => 'nullable|string|max:100',
            'user.email' => [
                'required',
                Rule::unique(User::class, 'email')->ignore($user),
            ],
        ]);

        $roleSlugs = \App\Support\RoleCatalog::resolveSlugs($request->input('user.roles', []));
        $isClientLike = collect($roleSlugs)->contains(fn ($slug) => \App\Support\RoleCatalog::isClientRole($slug));

        if ($isClientLike) {
            $request->validate([
                'user.projects' => 'required|array|min:1',
            ], [
                'user.projects.required' => 'Для клиента / заказчика выберите хотя бы один проект',
                'user.projects.min' => 'Для клиента / заказчика выберите хотя бы один проект',
            ]);
        }

        $permissions = collect($request->get('permissions'))
            ->map(fn ($value, $key) => [base64_decode($key) => $value])
            ->collapse()
            ->toArray();

        $user->when($request->filled('user.password'), function (Builder $builder) use ($request) {
            $builder->getModel()->password = Hash::make($request->input('user.password'));
        });

        $user
            ->fill($request->collect('user')->except(['password', 'permissions', 'roles', 'projects'])->toArray())
            ->forceFill(['permissions' => $permissions])
            ->save();

        $user->replaceRoles($request->input('user.roles'));

        if ($isClientLike) {
            $user->projects()->sync($request->input('user.projects', []));
        } else {
            $user->projects()->detach();
        }

        Toast::info('Пользователь сохранён');

        return redirect()->route('platform.systems.users');
    }

    /**
     * @throws \Exception
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function remove(User $user)
    {
        $user->delete();

        Toast::info(__('User was removed'));

        return redirect()->route('platform.systems.users');
    }

    /**
     * @return \Illuminate\Http\RedirectResponse
     */
    public function loginAs(User $user)
    {
        Impersonation::loginAs($user);

        Toast::info(__('You are now impersonating this user'));

        return redirect()->route(config('platform.index'));
    }
}
