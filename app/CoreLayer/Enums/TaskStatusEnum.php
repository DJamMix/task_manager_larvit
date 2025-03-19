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
     * Получить название статуса.
     */
    public function label(): string
    {
        return match ($this) {
            self::NEW => __('task.status.new'),
            self::IN_PROGRESS => __('task.status.in_progress'),
            self::COMPLETED => __('task.status.completed'),
            self::CANCELED => __('task.status.canceled'),
            self::POSTPONED => __('task.status.postponed'),
        };
    }

    /**
     * Получить цвет для статуса.
     * Используются конкретные цвета в формате HEX.
     */
    public function color(): string
    {
        return match ($this) {
            self::NEW => '#37b1ea',  // Синий
            self::IN_PROGRESS => '#ea7b37',  // Желтый
            self::COMPLETED => '#008000',  // Зеленый
            self::CANCELED => '#FF0000',  // Красный
            self::POSTPONED => '#808080',  // Серый
        };
    }

    /**
     * Получить список статусов с локализованными названиями.
     */
    public static function options(): array
    {
        return [
            self::NEW->value => self::NEW->label(),
            self::IN_PROGRESS->value => self::IN_PROGRESS->label(),
            self::COMPLETED->value => self::COMPLETED->label(),
            self::CANCELED->value => self::CANCELED->label(),
            self::POSTPONED->value => self::POSTPONED->label(),
        ];
    }
}
