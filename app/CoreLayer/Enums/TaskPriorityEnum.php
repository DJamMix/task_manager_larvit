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
        return match ($this) {
            self::EMERGENCY => 'Критический',
            self::BLOCKER => 'Блокирующий',
            self::HIGH => 'Высокий',
            self::MEDIUM => 'Обычный',
            self::LOW => 'Низкий',
            self::TRIVIAL => 'Минимальный',
        };
    }

    /**
     * Короткий код для быстрого сканирования списка (P0…P5).
     */
    public function code(): string
    {
        return match ($this) {
            self::EMERGENCY => 'P0',
            self::BLOCKER => 'P1',
            self::HIGH => 'P2',
            self::MEDIUM => 'P3',
            self::LOW => 'P4',
            self::TRIVIAL => 'P5',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::EMERGENCY => 'Чинить сейчас: прод лежит или теряем деньги',
            self::BLOCKER => 'Блокирует работу команды / клиента, срок до ~3 дней',
            self::HIGH => 'Важная задача — брать сразу после P0/P1',
            self::MEDIUM => 'Обычный приоритет, в порядке очереди',
            self::LOW => 'Можно отложить, если есть более важные',
            self::TRIVIAL => 'Когда освободится время',
        };
    }

    public function colorClass(): string
    {
        return match ($this) {
            self::EMERGENCY => 'priority-badge priority-badge--p0',
            self::BLOCKER => 'priority-badge priority-badge--p1',
            self::HIGH => 'priority-badge priority-badge--p2',
            self::MEDIUM => 'priority-badge priority-badge--p3',
            self::LOW => 'priority-badge priority-badge--p4',
            self::TRIVIAL => 'priority-badge priority-badge--p5',
        };
    }

    public function rowClass(): string
    {
        return match ($this) {
            self::EMERGENCY => 'task-row--p0',
            self::BLOCKER => 'task-row--p1',
            self::HIGH => 'task-row--p2',
            self::MEDIUM => 'task-row--p3',
            self::LOW => 'task-row--p4',
            self::TRIVIAL => 'task-row--p5',
        };
    }

    public function sortWeight(): int
    {
        return match ($this) {
            self::EMERGENCY => 0,
            self::BLOCKER => 1,
            self::HIGH => 2,
            self::MEDIUM => 3,
            self::LOW => 4,
            self::TRIVIAL => 5,
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::EMERGENCY => '🔥',
            self::BLOCKER => '⛔',
            self::HIGH => '▲',
            self::MEDIUM => '●',
            self::LOW => '▼',
            self::TRIVIAL => '·',
        };
    }

    public function badgeHtml(): string
    {
        return sprintf(
            '<span class="%s" title="%s"><span class="priority-badge__code">%s</span> %s</span>',
            e($this->colorClass()),
            e($this->description()),
            e($this->code()),
            e($this->label())
        );
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
            fn (array $options, self $priority) => $options + [
                $priority->value => sprintf('%s · %s — %s', $priority->code(), $priority->label(), $priority->description()),
            ],
            []
        );
    }
}
