<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class ProjectContext
{
    public const SESSION_KEY = 'active_project_id';

    public function id(): ?int
    {
        $id = Session::get(self::SESSION_KEY);

        if ($id === null) {
            return null;
        }

        $id = (int) $id;

        if (!$this->isAccessible($id)) {
            $this->clear();

            return null;
        }

        return $id;
    }

    public function project(): ?Project
    {
        $id = $this->id();

        return $id ? Project::find($id) : null;
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

        if (!$this->isAccessible($projectId)) {
            abort(403, 'Нет доступа к выбранному проекту');
        }

        Session::put(self::SESSION_KEY, $projectId);
    }

    public function clear(): void
    {
        Session::forget(self::SESSION_KEY);
    }

    public function availableProjects(): Collection
    {
        $user = Auth::user();

        if (!$user instanceof User) {
            return collect();
        }

        return $this->projectsForUser($user)->values();
    }

    public function projectsForUser(User $user): Collection
    {
        if ($user->hasAccess('platform.systems.projects') || $user->hasAccess('platform.systems.tasks')) {
            $query = Project::query()->orderBy('name');
            if (\Illuminate\Support\Facades\Schema::hasColumn('projects', 'is_active')) {
                $query->where('is_active', true);
            }

            return $query->get();
        }

        if (
            $user->inRole('client')
            || $user->inRole('client_employer')
            || $user->hasAccess('platform.systems.client.projects')
        ) {
            $query = $user->projects()->orderBy('name');
            if (\Illuminate\Support\Facades\Schema::hasColumn('projects', 'is_active')) {
                $query->where('is_active', true);
            }

            return $query->get();
        }

        // Также менеджер с актами видит все проекты
        if ($user->hasAccess('platform.systems.acts')) {
            $query = Project::query()->orderBy('name');
            if (\Illuminate\Support\Facades\Schema::hasColumn('projects', 'is_active')) {
                $query->where('is_active', true);
            }

            return $query->get();
        }

        // Сотрудник: проекты, где он участник или исполнитель задач
        $memberIds = $user->memberProjects()->pluck('projects.id');
        $executorIds = $user->assignedTasks()->distinct()->pluck('project_id');

        $ids = $memberIds->merge($executorIds)->unique()->filter()->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        $query = Project::query()->whereIn('id', $ids)->orderBy('name');

        if (\Illuminate\Support\Facades\Schema::hasColumn('projects', 'is_active')) {
            $query->where('is_active', true);
        }

        return $query->get();
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
}
