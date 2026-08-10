<?php

declare(strict_types=1);

namespace App\Orchid\Screens\Bot;

use App\Models\Bot;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Screen;
use Orchid\Screen\TD;
use Orchid\Support\Facades\Layout;

class BotListScreen extends Screen
{
    public function query(): iterable
    {
        return [
            'bots' => Bot::query()->with(['user', 'creator'])->orderByDesc('id')->paginate(30),
        ];
    }

    public function name(): ?string
    {
        return 'Боты';
    }

    public function description(): ?string
    {
        return 'Сервисные боты мессенджера (как в Telegram). Создавать и настраивать могут только администраторы.';
    }

    public function permission(): ?iterable
    {
        return ['platform.systems.bots'];
    }

    public function commandBar(): iterable
    {
        return [
            Link::make('API-документация')
                ->icon('bs.book')
                ->route('platform.systems.bots.docs'),

            Link::make('Создать бота')
                ->icon('bs.plus-circle')
                ->route('platform.systems.bots.create')
                ->class('btn btn-primary'),
        ];
    }

    public function layout(): iterable
    {
        return [
            Layout::table('bots', [
                TD::make('id', 'ID')->width('70px'),
                TD::make('name', 'Имя')
                    ->render(fn (Bot $b) => '<a href="'.route('platform.systems.bots.edit', $b).'">'
                        .e($b->name).'</a>'),
                TD::make('username', 'Username')
                    ->render(fn (Bot $b) => e($b->displayUsername())),
                TD::make('is_active', 'Статус')
                    ->render(fn (Bot $b) => $b->is_active
                        ? '<span class="badge text-bg-success">активен</span>'
                        : '<span class="badge text-bg-secondary">выключен</span>'),
                TD::make('webhook_url', 'Webhook')
                    ->render(fn (Bot $b) => $b->webhook_url
                        ? '<span class="text-success">задан</span>'
                        : '<span class="text-muted">getUpdates</span>'),
                TD::make('token_hint', 'Токен')
                    ->render(fn (Bot $b) => e($b->token_hint ?: '—')),
                TD::make('created_at', 'Создан')
                    ->render(fn (Bot $b) => optional($b->created_at)->format('d.m.Y H:i')),
            ]),
        ];
    }
}
