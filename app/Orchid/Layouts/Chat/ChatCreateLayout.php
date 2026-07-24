<?php

namespace App\Orchid\Layouts\Chat;

use App\Services\ChatService;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\Picture;
use Orchid\Screen\Fields\Select;
use Orchid\Screen\Fields\TextArea;
use Orchid\Screen\Layouts\Rows;

class ChatCreateLayout extends Rows
{
    protected $title;

    protected function fields(): iterable
    {
        $options = app(ChatService::class)->chatMemberOptions(auth()->id());

        return [
            Picture::make('chat.avatar_path')
                ->title('Аватар чата')
                ->storage('public')
                ->path('chat-avatars')
                ->targetRelativeUrl()
                ->acceptedFiles('image/jpeg,image/png,image/webp,image/gif'),

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
                ->help('Сотрудники и контакты клиентов. Групповой чат могут создавать только пользователи с правом «Чаты (создание)».')
                ->required(),
        ];
    }
}
