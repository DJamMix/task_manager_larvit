<?php

namespace App\CoreLayer\Enums;

enum TaskTypeEnum: string
{
    case DEFAULT = 'default';
    case BUG = 'bug';

    public function label(): string
    {
        return match($this) {
            self::DEFAULT => 'Обычная',
            self::BUG => 'Баг',
        };
    }

    public static function options(): array
    {
        return array_reduce(
            self::cases(),
            fn(array $options, self $type) => $options + [$type->value => $type->label()],
            []
        );
    }
}