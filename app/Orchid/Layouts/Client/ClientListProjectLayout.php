<?php

namespace App\Orchid\Layouts\Client;

use App\Models\Project;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Layouts\Table;
use Orchid\Screen\TD;

class ClientListProjectLayout extends Table
{
    /**
     * Data source.
     *
     * The name of the key to fetch it from the query.
     * The results of which will be elements of the table.
     *
     * @var string
     */
    protected $target = 'projects';

    /**
     * Get the table cells to be displayed.
     *
     * @return TD[]
     */
    protected function columns(): iterable
    {
        return [
            TD::make('name', 'Название проекта')
                ->render(function (Project $project) {
                    return Link::make($project->name)
                        ->route('platform.systems.client.project.tasks', ['project' => $project]);
                }),
            
            TD::make('tasks_count', 'Количество задач')
                ->render(function (Project $project) {
                    return $project->tasks()->count();
                }),
                
            TD::make('created_at', 'Дата создания')
                ->render(function (Project $project) {
                    return $project->created_at->format('d.m.Y H:i');
                }),
        ];
    }
}
