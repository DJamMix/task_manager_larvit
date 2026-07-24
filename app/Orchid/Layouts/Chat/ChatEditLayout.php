<?php

namespace App\Orchid\Layouts\Chat;

use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\Picture;
use Orchid\Screen\Fields\TextArea;
use Orchid\Screen\Layouts\Rows;

class ChatEditLayout extends Rows
{
    protected $title;

    protected function fields(): iterable
    {
        return [
            Picture::make('chat.avatar_path')
                ->title('Аватар чата')
                ->storage('public')
                ->path('chat-avatars')
                ->targetRelativeUrl()
                ->acceptedFiles('image/jpeg,image/png,image/webp,image/gif')
                ->help('Как в Telegram: своя картинка для группы'),

            Input::make('chat.title')
                ->title('Название')
                ->required()
                ->max(120),

            TextArea::make('chat.description')
                ->title('Описание')
                ->rows(3)
                ->placeholder('О чём этот чат'),
        ];
    }
}
