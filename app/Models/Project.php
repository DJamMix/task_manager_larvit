<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Orchid\Screen\AsSource;

class Project extends Model
{
    use HasFactory, AsSource;

    protected $fillable = [
        'name',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Связь многие-ко-многим с клиентами
     */
    public function clients(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'client_project');
    }

    /**
     * Сотрудники / участники команды проекта
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_members')
            ->withTimestamps();
    }

    /**
     * Связь с задачами проекта
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function acts(): HasMany
    {
        return $this->hasMany(Act::class);
    }

    public function progressStats(): array
    {
        $total = $this->tasks()->count();
        $done = $this->tasks()->whereIn('status', ['completed', 'unpaid', 'canceled'])->count();
        $active = $this->tasks()->whereNotIn('status', ['completed', 'unpaid', 'canceled', 'draft'])->count();
        $hoursSpent = (float) $this->tasks()->sum('hours_spent');
        $hoursEstimated = (float) $this->tasks()->sum('estimation_hours');

        return [
            'total' => $total,
            'done' => $done,
            'active' => $active,
            'percent' => $total > 0 ? (int) round(($done / $total) * 100) : 0,
            'hours_spent' => $hoursSpent,
            'hours_estimated' => $hoursEstimated,
        ];
    }
}
