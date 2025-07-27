<?php

namespace App\CoreLayer\Enums;

enum TaskStatusEnum: string
{
    case DRAFT = 'draft';
    case APPROVED = 'approved';
    case ESTIMATION = 'estimation';
    case ESTIMATION_REVIEW = 'estimation_review';
    case NEW = 'new';
    case IN_PROGRESS = 'in_progress';
    case TESTING_STAGE = 'testing_stage';
    case TESTING_PROD = 'testing_prod';
    case DEMO = 'demo';
    case UNPAID = 'unpaid';
    case COMPLETED = 'completed';
    case CANCELED = 'canceled';

    /**
     * Получить название статуса.
     */
    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Согласование',
            self::APPROVED => 'Согласована',
            self::ESTIMATION => 'Оценка',
            self::ESTIMATION_REVIEW => 'Согласование оценки',
            self::NEW => 'Новая',
            self::IN_PROGRESS => 'В работе',
            self::TESTING_STAGE => 'Тестируется на stage',
            self::TESTING_PROD => 'Тестируется на prod',
            self::DEMO => 'Демонстрация заказчику',
            self::UNPAID => 'Не оплачена',
            self::COMPLETED => 'Закрыта',
            self::CANCELED => 'Отменена',
        };
    }

    /**
     * Получить цвет для статуса.
     * Используются конкретные цвета в формате HEX.
     */
    public function color(): string
    {
        return match ($this) {
            self::DRAFT => '#FFA500',         // Оранжевый
            self::APPROVED => '#87CEEB',      // Голубой
            self::ESTIMATION => '#9370DB',    // Фиолетовый
            self::ESTIMATION_REVIEW => '#BA55D3', // Средний орхидейный
            self::NEW => '#37b1ea',           // Синий
            self::IN_PROGRESS => '#FFD700',   // Золотой
            self::TESTING_STAGE => '#32CD32', // Лаймовый
            self::TESTING_PROD => '#3CB371',  // Морской зеленый
            self::DEMO => '#FF69B4',         // Горячий розовый
            self::UNPAID => '#FF6347',        // Томатный
            self::COMPLETED => '#008000',     // Зеленый
            self::CANCELED => '#FF0000',      // Красный
        };
    }

    /**
     * Получить список статусов с локализованными названиями.
     */
    public static function options(): array
    {
        return [
            self::DRAFT->value => self::DRAFT->label(),
            self::APPROVED->value => self::APPROVED->label(),
            self::ESTIMATION->value => self::ESTIMATION->label(),
            self::ESTIMATION_REVIEW->value => self::ESTIMATION_REVIEW->label(),
            self::NEW->value => self::NEW->label(),
            self::IN_PROGRESS->value => self::IN_PROGRESS->label(),
            self::TESTING_STAGE->value => self::TESTING_STAGE->label(),
            self::TESTING_PROD->value => self::TESTING_PROD->label(),
            self::DEMO->value => self::DEMO->label(),
            self::UNPAID->value => self::UNPAID->label(),
            self::COMPLETED->value => self::COMPLETED->label(),
            self::CANCELED->value => self::CANCELED->label(),
        ];
    }
}
