<?php

namespace App\Orchid\Layouts\Chat;

use App\Services\ChatService;
use Orchid\Screen\Field;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\Select;
use Orchid\Screen\Fields\TextArea;
use Orchid\Screen\Layouts\Rows;

class ChatCreateLayout extends Rows
{
    protected $title;

    protected function fields(): iterable
    {
        $options = app(ChatService::class)->staffUserOptions(auth()->id());

        return [
            Input::make('chat.title')
                ->title('Название чата')
                ->placeholder('Например: Релиз 2.0')
                ->required()
                ->max(120),

            TextArea::make('chat.description')
                ->title('Описание')
                ->rows(2)
                ->placeholder('Опционально'),

            Select::make('chat.member_ids.')
                ->options($options)
                ->multiple()
                ->title('Участники')
                ->help('Можно добавить сотрудников, PM и админов. Клиентов добавить нельзя.')
                ->required(),
        ];
    }
}
