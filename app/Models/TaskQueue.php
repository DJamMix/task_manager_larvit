<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Orchid\Screen\AsSource;

class TaskQueue extends Model
{
    use AsSource;

    protected $fillable = [
        'key',
        'name',
        'description',
        'next_number',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'next_number' => 'integer',
    ];

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'queue_id');
    }

    public static function optionsForSelect(bool $activeOnly = true): array
    {
        static::ensureDefaults();

        $options = static::query()
            ->when($activeOnly, fn ($q) => $q->where('is_active', true))
            ->orderBy('key')
            ->get()
            ->mapWithKeys(fn (self $q) => [$q->id => "{$q->key} — {$q->name}"])
            ->all();

        // Если активных нет — покажем все, чтобы создание задачи не ломалось
        if ($options === [] && $activeOnly) {
            return static::optionsForSelect(false);
        }

        return $options;
    }

    /** Гарантирует базовые очереди (PHP / FRONTEND / DEVOPS). */
    public static function ensureDefaults(): void
    {
        if (static::query()->exists()) {
            return;
        }

        foreach ([
            ['key' => 'PHP', 'name' => 'Backend / PHP', 'description' => 'Задачи по бэкенду'],
            ['key' => 'FRONTEND', 'name' => 'Frontend', 'description' => 'Задачи по фронтенду'],
            ['key' => 'DEVOPS', 'name' => 'DevOps', 'description' => 'Инфраструктура и деплой'],
        ] as $row) {
            static::query()->firstOrCreate(
                ['key' => $row['key']],
                [
                    'name' => $row['name'],
                    'description' => $row['description'],
                    'next_number' => 1,
                    'is_active' => true,
                ]
            );
        }
    }

    /** Атомарно выдать следующий номер в очереди. */
    public function allocateNextNumber(): int
    {
        return (int) \Illuminate\Support\Facades\DB::transaction(function () {
            $queue = static::query()->whereKey($this->id)->lockForUpdate()->firstOrFail();
            $number = (int) $queue->next_number;
            $queue->next_number = $number + 1;
            $queue->save();

            return $number;
        });
    }
}
