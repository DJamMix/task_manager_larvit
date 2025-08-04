<?php

declare(strict_types=1);

namespace App\Orchid\Screens\User;

use App\Orchid\Layouts\User\ProfilePasswordLayout;
use App\Orchid\Layouts\User\UserEditLayout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Orchid\Access\Impersonation;
use App\Models\User;
use Orchid\Screen\Action;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Fields\TextArea;
use Orchid\Screen\Screen;
use Orchid\Support\Color;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;

class UserProfileScreen extends Screen
{
    /**
     * Fetch data to be displayed on the screen.
     *
     *
     * @return array
     */
    public function query(Request $request): iterable
    {
        return [
            'user' => $request->user(),
        ];
    }

    /**
     * The name of the screen displayed in the header.
     */
    public function name(): ?string
    {
        return __('project.account.my_account');
    }

    /**
     * Display header description.
     */
    public function description(): ?string
    {
        return 'Обновите данные своей учетной записи, такие как имя, адрес электронной почты и пароль';
    }

    /**
     * The screen's action buttons.
     *
     * @return Action[]
     */
    public function commandBar(): iterable
    {
        return [
            Button::make('Вернуться в свой аккаунт')
                ->novalidate()
                ->canSee(Impersonation::isSwitch())
                ->icon('bs.people')
                ->route('platform.switch.logout'),

            Button::make('Выход')
                ->novalidate()
                ->icon('bs.box-arrow-left')
                ->route('platform.logout'),
        ];
    }

    /**
     * @return \Orchid\Screen\Layout[]
     */
    public function layout(): iterable
    {
        return [
            Layout::block(UserEditLayout::class)
                ->title(__('Profile Information'))
                ->description(__("Update your account's profile information and email address."))
                ->commands(
                    Button::make(__('Save'))
                        ->type(Color::BASIC())
                        ->icon('bs.check-circle')
                        ->method('save')
                ),

            Layout::block(ProfilePasswordLayout::class)
                ->title(__('Update Password'))
                ->description(__('Ensure your account is using a long, random password to stay secure.'))
                ->commands(
                    Button::make(__('Update password'))
                        ->type(Color::BASIC())
                        ->icon('bs.check-circle')
                        ->method('changePassword')
                ),

            Layout::block(Layout::rows([
                TextArea::make('telegram_status')
                    ->title('Статус привязки')
                    ->value(function () {
                        $user = auth()->user();
                        return $user->telegram_id 
                            ? "✅ Аккаунт Telegram привязан (ID: {$user->telegram_id})"
                            : "❌ Аккаунт Telegram не привязан";
                    })
                    ->readonly()
                    ->style('color: ' . (auth()->user()->telegram_id ? 'green' : 'red')),
            ]))
                ->title('Привязка Telegram')
                ->description('Привяжите свой аккаунт Telegram для получения уведомлений')
                ->commands(
                    Button::make(__('Привязать Telegram'))
                        ->type(Color::SUCCESS())
                        ->icon('bs.paperclip')
                        ->method('bindTelegram')
                        ->canSee(!auth()->user()->telegram_id)
                ),
        ];
    }

    public function bindTelegram(Request $request)
    {
        $user = $request->user();
        
        if ($user->telegram_id === null) {
            $user->telegram_verification_code = bin2hex(random_bytes(6));
            $user->save();

            $botRedirectUrl = "https://t.me/crewdev_task_manage_bot?start={$user->telegram_verification_code}";

            $request->session()->flash('telegram_redirect_url', $botRedirectUrl);

            return redirect()->route('platform.telegram.connect');
        }

        return back();
    }

    public function save(Request $request): void
    {
        $request->validate([
            'user.name'  => 'required|string',
            'user.email' => [
                'required',
                Rule::unique(User::class, 'email')->ignore($request->user()),
            ],
        ]);

        $request->user()
            ->fill($request->get('user'))
            ->save();

        Toast::info(__('Profile updated.'));
    }

    public function changePassword(Request $request): void
    {
        $guard = config('platform.guard', 'web');
        $request->validate([
            'old_password' => 'required|current_password:'.$guard,
            'password'     => 'required|confirmed|different:old_password',
        ]);

        tap($request->user(), function ($user) use ($request) {
            $user->password = Hash::make($request->get('password'));
        })->save();

        Toast::info(__('Password changed.'));
    }
}
