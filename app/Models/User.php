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
