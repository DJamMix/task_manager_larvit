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

        if ($user->hasAccess('platform.systems.tasks')) {
            return route('platform.systems.tasks.edit', $this->entity);
        }

        if ($user->hasAccess('platform.systems.my_tasks')) {
            return route('platform.systems.my_tasks.view', $this->entity);
        }

        if ($user->hasAccess('platform.systems.client.project.tasks') && $this->entity->project) {
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

        if(!$user) {
            $this->entity->search('');
        }

        if($user->hasAccess('platform.systems.tasks')) {
            return $this->entity->search($query);
        } else if ($user->hasAccess('platform.systems.my_tasks')) {
            return $this->entity->search($query)->where('executor_id', $user->id)
                ->whereNotIn('status', [
                    TaskStatusEnum::COMPLETED->value,
                    TaskStatusEnum::CANCELED->value,
                    TaskStatusEnum::UNPAID->value,
                    TaskStatusEnum::DEMO->value,
                ]);
        } else if ($user->hasAccess('platform.systems.client.project.tasks')) {
            return $this->searchForClient($query, $user);
        } else {
            return $this->entity->search('');
        }
    }

    /**
     * Поиск для клиента - задачи проектов, где он является клиентом
     */
    protected function searchForClient(string $query = null, $user): Builder
    {
        // Получаем ID проектов, где пользователь является клиентом
        $clientProjectIds = $user->projects()->pluck('projects.id')->toArray();
        
        // Если у клиента нет проектов, возвращаем пустой результат
        if (empty($clientProjectIds)) {
            return $this->entity->search('');
        }

        // Ищем задачи, которые принадлежат проектам клиента
        return $this->entity->search($query)->whereIn('project_id', $clientProjectIds);
    }

    /**
     * @return int
     */
    public function perSearchShow(): int
    {
        return 5;
    }
}