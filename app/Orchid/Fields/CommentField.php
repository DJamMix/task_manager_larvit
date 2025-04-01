<?php

namespace App\Orchid\Fields;

use Orchid\Screen\Field;

class CommentField extends Field
{
    protected $view = 'platform.fields.comment';

    protected $attributes = [
        'author' => 'Неизвестно',
        'date' => null,
        'text' => '',
        'borderColor' => 'border-blue-500'
    ];

    protected $inlineAttributes = [
        'border-color'
    ];

    public function author(string $author): self
    {
        return $this->set('author', $author);
    }

    public function date($date): self
    {
        if ($date instanceof \DateTimeInterface) {
            $date = $date->format('d.m.Y H:i');
        }
        
        return $this->set('date', $date);
    }

    public function text(string $text): self
    {
        return $this->set('text', $text);
    }

    public function borderColor(string $color): self
    {
        return $this->set('borderColor', $color);
    }

    public function fromComment($comment): self
    {
        return $this
            ->author($comment->user->name ?? 'Неизвестно')
            ->date($comment->created_at ?? now())
            ->text($comment->text ?? '');
    }
}