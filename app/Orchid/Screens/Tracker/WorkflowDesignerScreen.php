<?php

namespace App\Orchid\Screens\Tracker;

use App\Models\WorkflowStatus;
use App\Services\WorkflowService;
use Illuminate\Http\Request;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;

class WorkflowDesignerScreen extends Screen
{
    public function query(WorkflowService $workflows, Request $request): iterable
    {
        config(['platform.workspace' => 'platform::workspace.full']);
        $workflows->bootstrapDefaults($request->user());

        return [
            'graph' => $workflows->graphPayload(),
            'save_url' => route('platform.systems.workflow.save'),
            'csrf' => csrf_token(),
            'statuses_count' => WorkflowStatus::query()->where('is_active', true)->count(),
        ];
    }

    public function name(): ?string
    {
        return 'Workflow статусов';
    }

    public function description(): ?string
    {
        return 'Схема переходов: соединяйте статусы стрелками';
    }

    public function permission(): ?iterable
    {
        return ['platform.systems.tasks'];
    }

    public function commandBar(): iterable
    {
        return [
            Link::make('Доска')->route('platform.systems.boards')->icon('bs.columns-gap'),
            Link::make('Спринты')->route('platform.systems.sprints')->icon('bs.lightning-charge'),
            Button::make('Сбросить к умолчанию')
                ->method('resetDefaults')
                ->confirm('Пересоздать стандартные переходы из enum? Существующие кастомные связи будут дополнены.')
                ->icon('bs.arrow-counterclockwise'),
        ];
    }

    public function layout(): iterable
    {
        return [
            Layout::view('orchid.layouts.tracker-workflow'),
        ];
    }

    public function resetDefaults(Request $request, WorkflowService $workflows)
    {
        $workflows->bootstrapDefaults($request->user());
        Toast::success('Базовый workflow обновлён');

        return back();
    }
}
