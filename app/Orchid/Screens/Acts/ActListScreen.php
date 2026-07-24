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
        $query = Act::with(['tasks', 'project'])->latest();

        if ($context->has()) {
            $query->where('project_id', $context->id());
        }

        return [
            'acts' => $query->paginate(10),
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
            ? 'Показаны акты активного проекта. При создании проект подставится сам.'
            : null;
    }

    public function permission(): ?iterable
    {
        return [
            'platform.systems.acts',
        ];
    }

    public function commandBar(): iterable
    {
        return [
            Link::make('Создать новый акт')
                ->icon('plus')
                ->route('platform.systems.acts.create'),
        ];
    }

    public function layout(): iterable
    {
        return [
            Layout::view('partials.project-context-banner'),
            ActListLayout::class,
        ];
    }
}
