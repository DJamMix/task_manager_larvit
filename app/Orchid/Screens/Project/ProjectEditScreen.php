<?php

namespace App\Orchid\Screens\Project;

use App\Models\Project;
use App\Orchid\Layouts\Project\ProjectEditLayout;
use Illuminate\Http\Request;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Toast;

class ProjectEditScreen extends Screen
{
    /**
     * @var Project
     */
    public $project;

    public function query(Project $project): iterable
    {
        $project->load('members');

        return [
            'project' => $project,
        ];
    }

    public function name(): ?string
    {
        return $this->project->exists ? 'Редактировать' : 'Создать';
    }

    public function permission(): ?iterable
    {
        return [
            'platform.systems.projects',
        ];
    }

    public function commandBar(): iterable
    {
        return [
            Button::make(__('project.remove.title'))
                ->icon('bs.trash3')
                ->confirm(__('project.remove.warning'))
                ->method('remove')
                ->canSee($this->project->exists),

            Button::make(__('project.save'))
                ->icon('bs.check-circle')
                ->method('save'),
        ];
    }

    public function layout(): iterable
    {
        return [
            ProjectEditLayout::class,
        ];
    }

    public function save(Request $request, Project $project)
    {
        $request->validate([
            'project.name' => 'required|string|max:255',
            'project.description' => 'nullable|string',
            'project.is_active' => 'nullable|boolean',
        ]);

        $data = $request->get('project');
        $memberIds = $data['members'] ?? [];
        unset($data['members']);

        if (!array_key_exists('is_active', $data)) {
            $data['is_active'] = true;
        }

        $project->fill($data);
        $project->save();
        $project->members()->sync(array_filter((array) $memberIds));

        Toast::info(__('model_project.save'));

        return redirect()->route('platform.systems.projects');
    }

    public function remove(Project $project)
    {
        $project->delete();

        Toast::info(__('model_project.remove'));

        return redirect()->route('platform.systems.projects');
    }
}
