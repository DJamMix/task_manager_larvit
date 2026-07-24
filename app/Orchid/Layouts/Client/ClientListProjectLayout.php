<?php

namespace App\Orchid\Layouts\Client;

use App\Models\Project;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Layouts\Table;
use Orchid\Screen\TD;

class ClientListProjectLayout extends Table
{
    protected $target = 'projects';

    protected function columns(): iterable
    {
        return [
            TD::make('name', 'Название проекта')
                ->render(function (Project $project) {
                    return Link::make($project->name)
                        ->route('platform.project-context.switch', ['project_id' => $project->id]);
                }),

            TD::make('progress', 'Прогресс')
                ->render(function (Project $project) {
                    $stats = $project->progressStats();

                    return sprintf(
                        '<div class="progress" style="height: 8px; min-width: 120px;">
                            <div class="progress-bar" role="progressbar" style="width: %d%%"></div>
                         </div>
                         <small class="text-muted">%d%% · %d из %d задач</small>',
                        $stats['percent'],
                        $stats['percent'],
                        $stats['done'],
                        $stats['total']
                    );
                }),

            TD::make('active', 'В работе')
                ->render(fn (Project $project) => $project->progressStats()['active']),

            TD::make('hours', 'Часы факт / оценка')
                ->render(function (Project $project) {
                    $stats = $project->progressStats();

                    return sprintf(
                        '%s / %s',
                        number_format($stats['hours_spent'], 1),
                        $stats['hours_estimated'] > 0
                            ? number_format($stats['hours_estimated'], 1)
                            : '—'
                    );
                }),

            TD::make('open', 'Открыть')
                ->render(function (Project $project) {
                    return Link::make('Задачи')
                        ->icon('bs.arrow-right')
                        ->route('platform.systems.client.project.tasks', ['project' => $project]);
                }),
        ];
    }
}
