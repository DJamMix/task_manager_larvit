<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\ProjectContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProjectContextController extends Controller
{
    public function switch(Request $request, ProjectContext $context): RedirectResponse
    {
        $projectId = $request->input('project_id');

        if ($projectId === null || $projectId === '') {
            $context->clear();
        } else {
            $context->set((int) $projectId);
        }

        $user = $request->user();

        // Для клиента сразу открываем задачи выбранного проекта
        if (
            $context->has()
            && $user
            && $user->hasAccess('platform.systems.client.project.tasks')
            && !$user->hasAccess('platform.systems.my_tasks')
            && !$user->hasAccess('platform.systems.tasks')
        ) {
            return redirect()->route('platform.systems.client.project.tasks', [
                'project' => $context->id(),
            ]);
        }

        return redirect()->back();
    }
}
