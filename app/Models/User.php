<?php

namespace App\Models;

use App\Support\RoleCatalog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Orchid\Attachment\Attachable;
use Orchid\Filters\Types\Like;
use Orchid\Filters\Types\Where;
use Orchid\Filters\Types\WhereDateStartEnd;
use Orchid\Platform\Models\User as Authenticatable;
use Orchid\Screen\AsSource;

class User extends Authenticatable
{
    use HasFactory, AsSource, Attachable;

    protected $fillable = [
        'name',
        'position',
        'email',
        'password',
        'telegram_id',
        'telegram_verification_code',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'permissions',
    ];

    protected $casts = [
        'permissions' => 'array',
        'email_verified_at' => 'datetime',
    ];

    protected $allowedFilters = [
        'id' => Where::class,
        'name' => Like::class,
        'position' => Like::class,
        'email' => Like::class,
        'updated_at' => WhereDateStartEnd::class,
        'created_at' => WhereDateStartEnd::class,
    ];

    protected $allowedSorts = [
        'id',
        'name',
        'position',
        'email',
        'updated_at',
        'created_at',
    ];

    public function createdTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'creator_id');
    }

    public function assignedTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'executor_id');
    }

    public function projects()
    {
        return $this->belongsToMany(Project::class, 'client_project');
    }

    public function memberProjects()
    {
        return $this->belongsToMany(Project::class, 'project_members')
            ->withTimestamps();
    }

    public function comments()
    {
        return $this->hasMany(Comment::class);
    }

    public function timeEntries()
    {
        return $this->hasMany(TrackingTime::class);
    }

    public function isClientAccount(): bool
    {
        return $this->roles->pluck('slug')->intersect(RoleCatalog::CLIENT_SLUGS)->isNotEmpty();
    }

    public function displayName(): string
    {
        if ($this->position) {
            return trim($this->name . ' · ' . $this->position);
        }

        return (string) $this->name;
    }

    public function avatarInitials(): string
    {
        $name = trim((string) $this->name);
        if ($name === '') {
            return '?';
        }

        $parts = preg_split('/\s+/u', $name) ?: [];
        if (count($parts) >= 2) {
            return mb_strtoupper(mb_substr($parts[0], 0, 1) . mb_substr($parts[1], 0, 1));
        }

        return mb_strtoupper(mb_substr($name, 0, 2));
    }

    /** Стабильный цвет аватарки (как в Bitrix / мессенджерах) */
    public function avatarColor(): string
    {
        $palette = [
            '#3b82f6', '#6366f1', '#8b5cf6', '#a855f7',
            '#ec4899', '#f43f5e', '#ef4444', '#f97316',
            '#eab308', '#22c55e', '#14b8a6', '#06b6d4',
        ];

        return $palette[(int) $this->id % count($palette)];
    }

    public function avatarUrl(): string
    {
        $email = strtolower(trim((string) $this->email));
        if ($email === '') {
            return '';
        }

        $hash = md5($email);

        return "https://www.gravatar.com/avatar/{$hash}?s=80&d=404";
    }

    public function roleLabels(): string
    {
        return $this->roles->pluck('name')->filter()->implode(' / ');
    }

    /**
     * Опции для Select: "Влад · Backend"
     *
     * @return array<int, string>
     */
    public static function optionsForSelect(?callable $queryCallback = null): array
    {
        $query = static::query()->orderBy('name');

        if ($queryCallback) {
            $queryCallback($query);
        }

        return $query->get(['id', 'name', 'position'])
            ->mapWithKeys(fn (self $user) => [$user->id => $user->displayName()])
            ->all();
    }
}
