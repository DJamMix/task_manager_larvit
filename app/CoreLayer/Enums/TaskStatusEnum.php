<?php

namespace App\CoreLayer\Enums;

enum TaskStatusEnum: string
{
    case NEW = 'New';
    case IN_PROGRESS = 'In_progress';
    case COMPLETED = 'Completed';
    case CANCELED = 'Canceled';
    case POSTPONED = 'Postponed';
    
    /**
     * Получить список статусов с локализованными названиями.
     */
    public static function labels(): array
    {
        return [
            self::NEW->value => __('task.status.new'),
            self::IN_PROGRESS->value => __('task.status.in_progress'),
            self::COMPLETED->value => __('task.status.completed'),
            self::CANCELED->value => __('task.status.canceled'),
            self::POSTPONED->value => __('task.status.postponed'),
        ];
    }

    /**
     * Получить название статуса.
     */
    public function label(): string
    {
        return self::labels()[$this->value] ?? $this->value;
    }
}
