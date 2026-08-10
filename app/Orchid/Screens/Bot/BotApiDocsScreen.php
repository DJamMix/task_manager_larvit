<?php

declare(strict_types=1);

namespace App\Orchid\Screens\Bot;

use Orchid\Screen\Actions\Link;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;

class BotApiDocsScreen extends Screen
{
    public function query(): iterable
    {
        return [
            'api_base' => url('/api/bot'),
            'example_token' => '123456789:AAHxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
        ];
    }

    public function name(): ?string
    {
        return 'Bot API — документация';
    }

    public function description(): ?string
    {
        return 'HTTP API для ботов мессенджера (совместимо по идее с Telegram Bot API)';
    }

    public function permission(): ?iterable
    {
        return ['platform.systems.bots'];
    }

    public function commandBar(): iterable
    {
        return [
            Link::make('К ботам')
                ->icon('bs.robot')
                ->route('platform.systems.bots'),
        ];
    }

    public function layout(): iterable
    {
        return [
            Layout::view('orchid.layouts.bot-api-docs'),
        ];
    }
}
