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
                ->toolbar(['text', 'list', 'format'])
                ->height('120px')
                ->placeholder('Написать сообщение…'),

            Upload::make('comment.attachments')
                ->title('Файлы')
                ->acceptedFiles('image/*,application/pdf,.zip,.rar,.doc,.docx,.xls,.xlsx,.txt')
                ->storage('public')
                ->maxFileSize(50)
                ->maxFiles(5),

            Select::make('comment.notify_user_ids.')
                ->options($participants)
                ->multiple()
                ->title('Уведомить')
                ->empty('Авто'),

            Button::make('Отправить')
                ->method('addComment')
                ->icon('bs.send')
                ->class('btn btn-primary'),
        ];
    }
}
