<?php

namespace App\Orchid\Layouts\Comment;

use App\Orchid\Fields\CommentField;
use Orchid\Screen\Field;
use Orchid\Screen\Fields\Label;
use Orchid\Screen\Layouts\Rows;

class CommentListLayout extends Rows
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
        $comments = $this->query->get('comments', []);

        if (empty($comments)) {
            return [
                CommentField::make('no_comments')
                    ->author('Система')
                    ->date(now())
                    ->text('Пока нет комментариев')
                    ->borderColor('border-gray-400')
            ];
        }

        $fields = [];
        foreach ($comments as $i => $comment) {
            $fields[] = CommentField::make("comment_{$comment['id']}")
                ->author($comment['user']['name'] ?? 'Неизвестно')
                ->date($comment['created_at'])
                ->text($comment['text'])
                ->borderColor($i % 2 ? 'border-blue-400' : 'border-indigo-400');
        }

        return $fields;
    }
}
