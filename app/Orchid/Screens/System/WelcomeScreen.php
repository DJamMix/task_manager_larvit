<?php

namespace App\Orchid\Screens\System;

use App\CoreLayer\Enums\TaskStatusEnum;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;

class WelcomeScreen extends Screen
{
    /**
     * Fetch data to be displayed on the screen.
     *
     * @return array
     */
    public function query(): iterable
    {
        $user = auth()->user();
        $projects = collect([]);

        if (auth()->check()) {
            $projects = $user->projects()
                ->limit(3)
                ->get()
                ->map(function ($project) {
                    $tasks = $project->tasks;
                    $totalTasks = $tasks->count();
                    
                    if ($totalTasks > 0) {
                        $completedTasks = $tasks->whereIn('status', [
                            TaskStatusEnum::COMPLETED->value,
                            TaskStatusEnum::CANCELED->value,
                            TaskStatusEnum::UNPAID->value,
                            TaskStatusEnum::DEMO->value,
                        ])->count();
                        
                        $progress = round(($completedTasks / $totalTasks) * 100);
                    } else {
                        $progress = 0;
                    }
                    
                    return [
                        'id' => $project->id,
                        'name' => $project->name,
                        'progress' => $progress,
                        'tasks_count' => $totalTasks,
                        'completed_tasks_count' => $completedTasks ?? 0
                    ];
                });
        }

        return [
            'user' => $user,
            'stats' => [
                'active_tasks' => $user->assignedTasks()->whereNotIn('status', [
                    TaskStatusEnum::COMPLETED->value,
                    TaskStatusEnum::CANCELED->value,
                    TaskStatusEnum::UNPAID->value,
                    TaskStatusEnum::DEMO->value,
                ])->count(),
                'completed_tasks' => $user->assignedTasks()->whereIn('status', [
                    TaskStatusEnum::COMPLETED->value,
                    TaskStatusEnum::CANCELED->value,
                    TaskStatusEnum::UNPAID->value,
                    TaskStatusEnum::DEMO->value,
                ])->count(),
            ],
            'projects' => $projects,
            'technologies' => [
                ['name' => 'Laravel', 'purpose' => 'Backend'],
                ['name' => 'Vue.js', 'purpose' => 'Frontend'],
                ['name' => 'Nuxt.js', 'purpose' => 'Frontend'],
            ]
        ];
    }

    /**
     * The name of the screen displayed in the header.
     *
     * @return string|null
     */
    public function name(): ?string
    {
        return 'CrewDev - Умный менеджер задач';
    }

    public function description(): ?string
    {
        return 'Эффективное управление проектами и задачами';
    }

    /**
     * The screen's action buttons.
     *
     * @return \Orchid\Screen\Action[]
     */
    public function commandBar(): iterable
    {
        return [];
    }

    /**
     * The screen's layout elements.
     *
     * @return \Orchid\Screen\Layout[]|string[]
     */
    public function layout(): iterable
    {
        return [
            Layout::view('platform.welcome'),
        ];
    }
}
