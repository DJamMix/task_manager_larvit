<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Session;

class ProjectContext
{
    public const SESSION_KEY = 'active_project_id';

    /** @var Collection<int, Project>|null */
    private ?Collection $availableCache = null;

    private ?int $resolvedId = null;

    private bool $idResolved = false;

    private static ?bool $projectsHaveIsActive = null;

    public function id(): ?int
    {
        if ($this->idResolved) {
            return $this->resolvedId;
        }

        $this->idResolved = true;
        $id = Session::get(self::SESSION_KEY);

        if ($id === null) {
            return $this->resolvedId = null;
        }

        $id = (int) $id;

        if (! $this->isAccessible($id)) {
            $this->clear();

            return $this->resolvedId = null;
        }

        return $this->resolvedId = $id;
    }

    public function project(): ?Project
    {
        $id = $this->id();
        if (! $id) {
            return null;
        }

        $fromList = $this->availableProjects()->firstWhere('id', $id);
        if ($fromList instanceof Project) {
            return $fromList;
        }

        return Project::query()->find($id);
    }

    public function has(): bool
    {
        return $this->id() !== null;
    }

    public function set(?int $projectId): void
    {
        if ($projectId === null) {
            $this->clear();

            return;
        }

        if (! $this->isAccessible($projectId)) {
            abort(403, 'Нет доступа к выбранному проекту');
        }

        Session::put(self::SESSION_KEY, $projectId);
        $this->resolvedId = $projectId;
        $this->idResolved = true;
    }

    public function clear(): void
    {
        Session::forget(self::SESSION_KEY);
        $this->resolvedId = null;
        $this->idResolved = true;
    }

    public function availableProjects(): Collection
    {
        if ($this->availableCache !== null) {
            return $this->availableCache;
        }

        $user = Auth::user();

        if (! $user instanceof User) {
            return $this->availableCache = collect();
        }

        return $this->availableCache = $this->projectsForUser($user)->values();
    }

    public function projectsForUser(User $user): Collection
    {
        $cacheKey = 'project_context.user.'.$user->id;
        $ttl = 60;

        return Cache::remember($cacheKey, $ttl, function () use ($user) {
            if ($user->hasAccess('platform.systems.projects') || $user->hasAccess('platform.systems.tasks')) {
                return $this->activeProjectsQuery()->orderBy('name')->get();
            }

            if (
                $user->inRole('client')
                || $user->inRole('client_employer')
                || $user->hasAccess('platform.systems.client.projects')
            ) {
                $query = $user->projects()->orderBy('name');
                $this->applyActiveFilter($query);

                return $query->get();
            }

            if ($user->hasAccess('platform.systems.acts')) {
                return $this->activeProjectsQuery()->orderBy('name')->get();
            }

            // Сотрудник: один UNION вместо двух pluck + whereIn
            $member = DB::table('project_members')
                ->where('user_id', $user->id)
                ->select('project_id');

            $assigned = DB::table('tasks')
                ->where('executor_id', $user->id)
                ->whereNotNull('project_id')
                ->distinct()
                ->select('project_id');

            $ids = $member->union($assigned)->pluck('project_id')->filter()->unique()->values();

            if ($ids->isEmpty()) {
                return collect();
            }

            $query = Project::query()->whereIn('id', $ids)->orderBy('name');
            $this->applyActiveFilter($query);

            return $query->get();
        });
    }

    public function isAccessible(int $projectId): bool
    {
        return $this->availableProjects()->contains('id', $projectId);
    }

    /**
     * Применить фильтр активного проекта к query builder задач.
     */
    public function applyToTaskQuery($query)
    {
        if ($this->has()) {
            $query->where('project_id', $this->id());
        }

        return $query;
    }

    /**
     * ID проекта для новых сущностей (если выбран контекст).
     */
    public function defaultProjectId(): ?int
    {
        return $this->id();
    }

    /** Сбросить кэш списка проектов пользователя (после смены состава проекта и т.п.). */
    public function forgetUserCache(?int $userId = null): void
    {
        $userId ??= Auth::id();
        if ($userId) {
            Cache::forget('project_context.user.'.$userId);
        }
        $this->availableCache = null;
        $this->idResolved = false;
        $this->resolvedId = null;
    }

    private function activeProjectsQuery()
    {
        $query = Project::query();
        $this->applyActiveFilter($query);

        return $query;
    }

    private function applyActiveFilter($query): void
    {
        if (self::$projectsHaveIsActive === null) {
            self::$projectsHaveIsActive = Cache::remember(
                'schema.projects.is_active',
                86400,
                fn () => Schema::hasColumn('projects', 'is_active')
            );
        }

        if (self::$projectsHaveIsActive) {
            $query->where('is_active', true);
        }
    }
}
