<?php

namespace App\Models;

use App\Support\RoleCatalog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Sanctum\HasApiTokens;
use Orchid\Attachment\Attachable;
use Orchid\Filters\Types\Like;
use Orchid\Filters\Types\Where;
use Orchid\Filters\Types\WhereDateStartEnd;
use Orchid\Platform\Models\User as Authenticatable;
use Orchid\Screen\AsSource;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, AsSource, Attachable;

    protected $fillable = [
        'name',
        'position',
        'avatar_path',
        'email',
        'password',
        'telegram_id',
        'telegram_verification_code',
        'ui_preferences',
        'is_bot',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'permissions',
    ];

    protected $casts = [
        'permissions' => 'array',
        'ui_preferences' => 'array',
        'email_verified_at' => 'datetime',
        'is_bot' => 'boolean',
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

    public function bot(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Bot::class);
    }

    public function isBot(): bool
    {
        return (bool) $this->is_bot;
    }

    public function timeEntries()
    {
        return $this->hasMany(TrackingTime::class);
    }

    public function isClientAccount(): bool
    {
        return $this->roles->pluck('slug')->intersect(RoleCatalog::CLIENT_SLUGS)->isNotEmpty();
    }

    /** Контакт клиента — только чаты + наблюдение за выбранными задачами */
    public function isClientContact(): bool
    {
        return $this->roles->pluck('slug')->contains('client_contact');
    }

    /** Клиент / заказчик — доступ к задачам своих проектов */
    public function isClientWithTaskAccess(): bool
    {
        return $this->roles->pluck('slug')
            ->intersect(['client', 'client_employer'])
            ->isNotEmpty();
    }

    public function displayName(): string
    {
        if ($this->is_bot) {
            $username = $this->relationLoaded('bot')
                ? $this->bot?->username
                : $this->bot()->value('username');

            return $username ? $this->name.' (@'.$username.')' : (string) $this->name;
        }

        if ($this->position) {
            return trim($this->name.' · '.$this->position);
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
        if (!empty($this->avatar_path)) {
            return $this->resolveAvatarPath((string) $this->avatar_path);
        }

        // Без внешних fallback (Gravatar d=404 мигал: load → 404 → initials).
        // Нет загруженного фото — только стабильные инициалы.
        return '';
    }

    public static function resolveStoredAvatarPath(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            return '';
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        if (str_starts_with($path, '/')) {
            return url($path);
        }

        // Orchid Picture иногда кладёт "storage/..." уже с префиксом
        if (str_starts_with($path, 'storage/')) {
            return asset($path);
        }

        return asset('storage/' . ltrim($path, '/'));
    }

    private function resolveAvatarPath(string $path): string
    {
        return static::resolveStoredAvatarPath($path);
    }

    public function roleLabels(): string
    {
        return $this->roles->pluck('name')->filter()->implode(' / ');
    }

    public function uiPreference(string $key, mixed $default = null): mixed
    {
        try {
            $prefs = $this->ui_preferences ?? [];
        } catch (\Throwable) {
            return $default;
        }

        return data_get($prefs, $key, $default);
    }

    public function setUiPreference(string $key, mixed $value): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasColumn($this->getTable(), 'ui_preferences')) {
            return;
        }

        $prefs = $this->ui_preferences ?? [];
        data_set($prefs, $key, $value);
        $this->ui_preferences = $prefs;
        $this->save();
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
