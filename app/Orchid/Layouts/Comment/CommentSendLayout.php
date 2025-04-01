<?php

namespace App\Orchid\Layouts\Comment;

use Orchid\Screen\Actions\Button;
use Orchid\Screen\Field;
use Orchid\Screen\Fields\Quill;
use Orchid\Screen\Layouts\Rows;

class CommentSendLayout extends Rows
{
    /**
     * Used to create the title of a group of form elements.
     *
     * @var string|null
     */
    protected $title;

    /**
     * Get the fields elements to be displayed.
     *
     * @return Field[]
     */
    protected function fields(): iterable
    {
        return [
            Quill::make('comment.text')
                ->toolbar(["text", "color", "header", "list", "format"])
                ->title('Комментарии'),

            Button::make('Отправить')
                ->method('addComment')
                ->icon('paper-plane')
                ->class('btn btn-primary'),
        ];
    }
}
