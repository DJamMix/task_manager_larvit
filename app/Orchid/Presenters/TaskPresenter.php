<?php

namespace App\Orchid\Presenters;

use App\CoreLayer\Enums\TaskPriorityEnum;
use App\CoreLayer\Enums\TaskStatusEnum;
use Laravel\Scout\Builder;
use Orchid\Screen\Contracts\Searchable;
use Orchid\Support\Presenter;

class TaskPresenter extends Presenter implements Searchable
{
    /**
     * @return string
     */
    public function label(): string
    {
        return 'Задачи';
    }

    /**
     * @return string
     */
    public function title(): string
    {
        return $this->entity->name;
    }

    /**
     * @return string
     */
    public function subTitle(): string
    {
        $description = $this->entity->description ?? 'Нет описания';
        
        // Убираем HTML теги
        $cleanDescription = strip_tags($description);
        
        // Обрезаем длинное описание
        if (mb_strlen($cleanDescription) > 100) {
            $cleanDescription = mb_substr($cleanDescription, 0, 100) . '...';
        }
        
        // Добавляем информацию о проекте и статусе
        $parts = [];
        
        if ($this->entity->project) {
            $parts[] = 'Проект: ' . $this->entity->project->name;
        }
        
        if ($this->entity->status) {
            $statusEnum = TaskStatusEnum::tryFrom($this->entity->status);
            if ($statusEnum) {
                $statusIcon = $this->getStatusIcon($statusEnum);
                $parts[] = $statusIcon . ' ' . $statusEnum->label();
            }
        }
        
        if ($this->entity->priority) {
            $priorityEnum = TaskPriorityEnum::tryFrom($this->entity->priority);
            if ($priorityEnum) {
                $parts[] = $priorityEnum->icon() . ' ' . $priorityEnum->label();
            }
        }
        
        $additionalInfo = implode(' | ', $parts);
        
        return $cleanDescription . ($additionalInfo ? " | " . $additionalInfo : "");
    }

    protected function getStatusIcon(TaskStatusEnum $status): string
    {
        return match ($status) {
            TaskStatusEnum::DRAFT => '📝',
            TaskStatusEnum::APPROVED => '✅',
            TaskStatusEnum::ESTIMATION => '⏱️',
            TaskStatusEnum::ESTIMATION_REVIEW => '👀',
            TaskStatusEnum::NEW => '🆕',
            TaskStatusEnum::IN_PROGRESS => '🔄',
            TaskStatusEnum::TESTING_STAGE => '🧪',
            TaskStatusEnum::TESTING_PROD => '🚀',
            TaskStatusEnum::DEMO => '📊',
            TaskStatusEnum::UNPAID => '💳',
            TaskStatusEnum::COMPLETED => '🏁',
            TaskStatusEnum::CANCELED => '❌',
        };
    }

    /**
     * @return string
     */
    public function url(): string
    {
        $user = auth()->user();

        if (!$user) {
            return '#';
        }

        if ($user->inRole('admin')) {
            return route('platform.systems.tasks.edit', $this->entity);
        }

        if ($user->inRole('employee')) {
            return route('platform.systems.my_tasks.view', $this->entity);
        }

        if ($user->inRole('client') && $this->entity->project) {
            return route('platform.systems.client.project.tasks.view', [
                'project' => $this->entity->project,
                'task' => $this->entity
            ]);
        }

        return '#';
    }

    /**
     * @return string|null
     */
    public function image(): ?string
    {
        return null;
    }
    
    /**
     * @param string|null $query
     *
     * @return Builder
     */
    public function searchQuery(string $query = null): Builder
    {
        $user = auth()->user();

        if (!$user || !$this->userHasSearchAccess($user)) {
            return $this->emptySearch($query);
        }

        $builder = $this->entity->newQuery();

        if ($user->inRole('admin')) {
            // Без дополнительных фильтров
        } else if ($user->inRole('employee')) {
            $builder->where(function($q) use ($user) {
                $q->where('executor_id', $user->id)
                ->orWhere('creator_id', $user->id)
                ->orWhereJsonContains('observers_ids', (string)$user->id);
            });
        } else if ($user->inRole('client')) {
            $builder->whereHas('project', function($projectQuery) use ($user) {
                $projectQuery->whereHas('clients', function($clientQuery) use ($user) {
                    $clientQuery->where('user_id', $user->id);
                })->orWhere('creator_id', $user->id); // или проекты, которые он создал
            });
        } else {
            return $this->emptySearch($query);
        }

        if (!empty($query)) {
            $builder->where(function($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('description', 'like', "%{$query}%")
                  ->orWhereHas('project', function($projectQuery) use ($query) {
                      $projectQuery->where('name', 'like', "%{$query}%");
                  })
                  ->orWhereHas('executor', function($userQuery) use ($query) {
                      $userQuery->where('name', 'like', "%{$query}%");
                  });
            });
        }

        $builder->with(['project', 'executor', 'category'])
                ->orderBy('created_at', 'desc')
                ->limit($this->perSearchShow());

        return new \Laravel\Scout\Builder(
            $this->entity, 
            $query,
            function() use ($builder) {
                return $builder->get();
            }
        );
    }

    /**
     * Проверяет, имеет ли пользователь доступ к поиску задач
     */
    protected function userHasSearchAccess($user): bool
    {
        return $user->inRole('admin') || 
               $user->inRole('employee') || 
               $user->inRole('client');
    }

    /**
     * Пустой поиск (для неавторизованных или без прав)
     */
    protected function emptySearch(string $query = null): Builder
    {
        return new \Laravel\Scout\Builder($this->entity, $query, function() {
            return collect();
        });
    }

    /**
     * @return int
     */
    public function perSearchShow(): int
    {
        return 5;
    }
}