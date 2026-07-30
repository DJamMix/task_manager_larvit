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
        return static::query()
            ->when($activeOnly, fn ($q) => $q->where('is_active', true))
            ->orderBy('key')
            ->get()
            ->mapWithKeys(fn (self $q) => [$q->id => "{$q->key} — {$q->name}"])
            ->all();
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
