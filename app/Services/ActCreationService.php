<?php

namespace App\Services;

use App\Models\Act;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

class ActCreationService
{
    public function validateMainData(Request $request): array
    {
        return $request->validate([
            'act.number' => 'required|string|unique:acts,number',
            'act.date' => 'required|date',
            'act.customer' => 'required|string',
            'act.executor' => 'required|string',
            'project_id' => 'required|exists:projects,id',
        ], [
            'act.number.required' => 'Поле "Номер акта" обязательно для заполнения.',
            'act.number.unique' => 'Акт с таким номером уже существует.',
            'act.date.required' => 'Поле "Дата акта" обязательно для заполнения.',
            'act.date.date' => 'Поле "Дата акта" должно быть датой.',
            'act.customer.required' => 'Поле "Заказчик" обязательно для заполнения.',
            'act.executor.required' => 'Поле "Исполнитель" обязательно для заполнения.',
            'project_id.required' => 'Необходимо выбрать проект.',
            'project_id.exists' => 'Выбранный проект не существует.',
        ]);
    }

    public function saveMainDataToSession(array $validatedData): void
    {
        $actData = [
            'number' => $validatedData['act']['number'],
            'date' => $validatedData['act']['date'],
            'customer' => $validatedData['act']['customer'],
            'executor' => $validatedData['act']['executor'],
            'info' => $validatedData['act']['info'] ?? '',
            'project_id' => $validatedData['project_id'],
        ];

        Log::info('Сохранение данных акта в сессию:', $actData);
        Session::put('act_data', $actData);
    }

    public function getTasksForSelection(int $projectId, array $selectedIds = [], ?int $currentActId = null): array
    {
        $tasks = Task::where('project_id', $projectId)
            ->with([
                'project:id,name',
                'executor:id,name',
                'acts' => function($query) use ($currentActId) {
                    $query->where('acts.id', '!=', $currentActId)
                          ->select('acts.id', 'acts.number', 'acts.date');
                }
            ])
            ->get()
            ->map(function($task) use ($selectedIds, $currentActId) {
                $isSelected = !empty($selectedIds) && in_array($task->id, $selectedIds);
                
                if (!$isSelected && Session::has('selected_task_ids')) {
                    $sessionIds = Session::get('selected_task_ids', []);
                    $isSelected = in_array($task->id, $sessionIds);
                }
                
                $projectName = 'Без проекта';
                if ($task->project) {
                    $projectName = $task->project->name;
                }
                
                $estimationHours = (float) $task->estimation_hours;
                if ($estimationHours < 0.25) {
                    $estimationHours = 0.25;
                }
                
                $usedInActs = [];
                if ($task->acts && $task->acts->isNotEmpty()) {
                    $usedInActs = $task->acts->map(function($act) {
                        return [
                            'id' => $act->id,
                            'number' => $act->number,
                            'date' => $act->date->format('d.m.Y'),
                        ];
                    })->toArray();
                }
                
                return [
                    'id' => $task->id,
                    'title' => $task->name,
                    'name' => $task->name,
                    'status' => $task->status,
                    'estimation_hours' => $estimationHours,
                    'hours' => $estimationHours,
                    'project' => $projectName,
                    'executor' => $task->executor->name ?? 'Не назначен',
                    'selected' => $isSelected,
                    'used_in_acts' => $usedInActs,
                    'has_duplicates' => !empty($usedInActs),
                ];
            })->toArray();
        
        return $tasks;
    }

    public function checkTaskDuplicates(array $selectedTaskIds, ?int $currentActId = null): array
    {
        $duplicates = [];
        
        foreach ($selectedTaskIds as $taskId) {
            $task = Task::with(['acts' => function($query) use ($currentActId) {
                $query->where('acts.id', '!=', $currentActId);
            }])->find($taskId);
            
            if ($task && $task->acts->isNotEmpty()) {
                $duplicates[$taskId] = $task->acts->map(function($act) {
                    return [
                        'id' => $act->id,
                        'number' => $act->number,
                        'date' => $act->date->format('d.m.Y'),
                    ];
                })->toArray();
            }
        }
        
        return $duplicates;
    }

    public function processSelectedTasks(array $tasksData): array
    {
        $selectedTasks = [];
        $taskHours = [];

        foreach ($tasksData as $task) {
            if (!($task['selected'] ?? false)) {
                continue;
            }

            $taskId = $task['id'] ?? null;
            $hours = $task['hours'] ?? 0;

            if (!$taskId) {
                continue;
            }

            $hours = (float) $hours;
            if ($hours < 0.25) {
                $hours = 0.25;
            }
            
            $selectedTasks[$taskId] = ['hours' => $hours];
            $taskHours[$taskId] = $hours;
        }

        return [
            'selectedTasks' => $selectedTasks,
            'totalHours' => (float) array_sum($taskHours),
            'totalTasks' => count($selectedTasks),
        ];
    }

    public function createAct(array $actData, array $selectedTasks): Act
    {
        DB::beginTransaction();

        try {
            $act = new Act();
            $act->number = $actData['number'];
            $act->date = $actData['date'];
            $act->customer = $actData['customer'];
            $act->executor = $actData['executor'];
            $act->info = $actData['info'] ?? '';
            $act->project_id = $actData['project_id'] ?? null;
            $act->total_hours = $selectedTasks['totalHours'];
            $act->total_tasks = $selectedTasks['totalTasks'];
            $act->status = 'generated';
            $act->generated_at = now();
            
            $act->save();
            $act->tasks()->sync($selectedTasks['selectedTasks']);
            
            DB::commit();
            
            Log::info('Акт успешно создан', [
                'act_id' => $act->id,
                'number' => $act->number,
                'project_id' => $act->project_id,
                'total_hours' => $act->total_hours,
            ]);
            
            return $act;
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Ошибка создания акта в транзакции', [
                'error' => $e->getMessage(),
                'act_data' => $actData,
            ]);
            throw $e;
        }
    }

    public function clearSessionData(): void
    {
        Session::forget(['act_data', 'selected_tasks']);
    }

    public function getSessionData(): ?array
    {
        return Session::get('act_data');
    }
}