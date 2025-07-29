<?php

namespace App\Orchid\Screens\Telegram;

use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;

class TelegramConnectScreen extends Screen
{
    /**
     * Fetch data to be displayed on the screen.
     *
     * @return array
     */
    public function query(): iterable
    {
        return [
            'url' => session('telegram_redirect_url'),
            'code' => auth()->user()->telegram_verification_code,
        ];
    }

    /**
     * The name of the screen displayed in the header.
     *
     * @return string|null
     */
    public function name(): ?string
    {
        return 'Подключение Telegram';
    }

    /**
     * The screen's action buttons.
     *
     * @return \Orchid\Screen\Action[]
     */
    public function commandBar(): iterable
    {
        return [];
    }

    /**
     * The screen's layout elements.
     *
     * @return \Orchid\Screen\Layout[]|string[]
     */
    public function layout(): iterable
    {
        return [
            Layout::view('telegram_connect', [
                'url' => session('telegram_redirect_url'),
                'code' => auth()->user()->telegram_verification_code,
            ]),
        ];
    }
}
