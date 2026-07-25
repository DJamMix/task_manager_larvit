<?php

namespace App\Orchid\Screens\Acts;

use App\Models\Act;
use App\Orchid\Layouts\Act\ActListLayout;
use App\Services\ProjectContext;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;

class ActListScreen extends Screen
{
    public function query(ProjectContext $context): iterable
    {
        $query = Act::with(['tasks', 'project'])->latest('date')->latest('id');

        if ($context->has()) {
            $query->where('project_id', $context->id());
        }

        return [
            'acts' => $query->paginate(15),
            'has_project_context' => $context->has(),
        ];
    }

    public function name(): ?string
    {
        $project = app(ProjectContext::class)->project();

        return $project ? 'Акты — ' . $project->name : 'Акты';
    }

    public function description(): ?string
    {
        return app(ProjectContext::class)->has()
            ? 'Акты выбранного проекта. При создании проект подставится автоматически.'
            : 'Акты выполненных работ по проектам.';
    }

    public function permission(): ?iterable
    {
        return ['platform.systems.acts'];
    }

    public function commandBar(): iterable
    {
        $context = app(ProjectContext::class);
        $createUrl = $context->has()
            ? route('platform.systems.acts.create', ['project_id' => $context->id()])
            : route('platform.systems.acts.create');

        return [
            Link::make('Составить акт')
                ->icon('bs.file-earmark-plus')
                ->href($createUrl)
                ->class('btn btn-primary'),
        ];
    }

    public function layout(): iterable
    {
        return [
            Layout::view('partials.project-context-banner'),
            Layout::view('orchid.layouts.act-list-intro'),
            ActListLayout::class,
        ];
    }
}
