<?php

namespace App\Orchid\Layouts\Comment;

use Orchid\Screen\Actions\Button;
use Orchid\Screen\Field;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\Quill;
use Orchid\Screen\Fields\Select;
use Orchid\Screen\Fields\Upload;
use Orchid\Screen\Layouts\Rows;

class DiscussionComposerLayout extends Rows
{
    protected $title;

    protected function fields(): iterable
    {
        $participants = $this->query->get('notify_options', []);

        return [
            Input::make('comment.parent_id')
                ->type('hidden')
                ->id('comment-parent-id'),

            Quill::make('comment.text')
                ->toolbar(['text', 'color', 'header', 'list', 'format', 'media'])
                ->title('Сообщение')
                ->placeholder('Напишите ответ коллегам или клиенту…')
                ->help('Форматирование, вложения и ответ на сообщение — как в обычном таск-менеджере'),

            Upload::make('comment.attachments')
                ->title('Прикрепить файлы или фото')
                ->acceptedFiles('image/*,application/pdf,.zip,.rar,.doc,.docx,.xls,.xlsx,.psd,.fig,.txt')
                ->storage('public')
                ->maxFileSize(50)
                ->help('Скриншоты и документы до 50 МБ'),

            Select::make('comment.notify_user_ids.')
                ->options($participants)
                ->multiple()
                ->title('Уведомить дополнительно')
                ->empty('Авто: участники / автор ответа')
                ->help('При «Ответить» автор исходного сообщения получит уведомление сам'),

            Button::make('Отправить')
                ->method('addComment')
                ->icon('bs.send')
                ->class('btn btn-primary btn-lg'),
        ];
    }
}
