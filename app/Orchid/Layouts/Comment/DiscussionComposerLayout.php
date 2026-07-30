<?php

namespace App\Orchid\Layouts\Comment;

use App\Support\UploadLimits;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Field;
use Orchid\Screen\Fields\Group;
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
                ->toolbar(['text', 'list', 'quote', 'format'])
                ->height('110px')
                ->placeholder('Сообщение… Код: </> или ```php … ```'),

            Group::make([
                Upload::make('comment.attachments')
                    ->title('Файлы')
                    ->acceptedFiles('image/*,application/pdf,.zip,.rar,.doc,.docx,.xls,.xlsx,.txt,.php,.js,.ts,.json,.sql,.css')
                    ->storage('public')
                    ->maxFileSize(UploadLimits::maxMb(256))
                    ->maxFiles(5),

                Select::make('comment.notify_user_ids.')
                    ->options($participants)
                    ->multiple()
                    ->title('Уведомить')
                    ->empty('Авто'),
            ])->fullWidth(),

            Button::make('Отправить')
                ->method('addComment')
                ->icon('bs.send')
                ->class('btn btn-primary btn-sm'),
        ];
    }
}
