<?php

namespace App\CoreLayer\Enums;

enum TaskPriorityEnum: string
{
    case EMERGENCY = 'emergency';
    case BLOCKER = 'blocker';
    case HIGH = 'high';
    case MEDIUM = 'medium';
    case LOW = 'low';
    case TRIVIAL = 'trivial';

    public function label(): string
    {
        return match($this) {
            self::EMERGENCY => 'Авария',
            self::BLOCKER => 'Блокер',
            self::HIGH => 'Высокий',
            self::MEDIUM => 'Средний',
            self::LOW => 'Низкий',
            self::TRIVIAL => 'Не важная',
        };
    }

    public function description(): string
    {
        return match($this) {
            self::EMERGENCY => 'Самая важная задача. Требуется немедленное решение (hotfix), так как система работает в корне неверно, что приводит к потерям прибыли',
            self::BLOCKER => 'Функционал работает неверно, много жалоб. Все задачи блокируются, на решение обычно до 3 дней',
            self::HIGH => 'Задача берётся в первую очередь при отсутствии блокеров и аварий',
            self::MEDIUM => 'Берётся при отсутствии задач с более высоким приоритетом',
            self::LOW => 'Берётся когда нет задач с более высоким приоритетом',
            self::TRIVIAL => 'Задача без срочности, можно откладывать пока есть более важные задачи',
        };
    }

    public function colorClass(): string
    {
        return match($this) {
            self::EMERGENCY => 'bg-danger text-white',    // Красный
            self::BLOCKER => 'bg-danger-light text-dark', // Светло-красный
            self::HIGH => 'bg-warning text-white',        // Оранжевый
            self::MEDIUM => 'bg-info text-white',         // Синий
            self::LOW => 'bg-primary-light text-dark',    // Светло-синий
            self::TRIVIAL => 'bg-light text-dark',        // Светлый
        };
    }

    public function icon(): string
    {
        return match($this) {
            self::EMERGENCY => '🔥', // Огонь
            self::BLOCKER => '⛔',   // Стоп
            self::HIGH => '⚠️',      // Внимание
            self::MEDIUM => '🔵',    // Синий круг
            self::LOW => '🔹',       // Маленький синий ромб
            self::TRIVIAL => '⚪',   // Белый круг
        };
    }

    public static function orderedCases(): array
    {
        return [
            self::EMERGENCY,
            self::BLOCKER,
            self::HIGH,
            self::MEDIUM,
            self::LOW,
            self::TRIVIAL,
        ];
    }

    public static function options(): array
    {
        return array_reduce(
            self::orderedCases(),
            fn(array $options, self $priority) => $options + [
                $priority->value => $priority->label()
            ],
            []
        );
    }
}