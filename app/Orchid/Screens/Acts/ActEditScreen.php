<?php

namespace App\Orchid\Screens\Acts;

use App\Models\Act;
use App\Models\Project;
use App\Orchid\Layouts\Act\ActEditLayout;
use App\Services\ActCreationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Alert;
use Orchid\Support\Facades\Toast;
use Orchid\Support\Color;
use WordForLaravel\Facades\WordForLaravel;

class ActEditScreen extends Screen
{
    public $name = 'Создание акта';
    public $description = 'Создание нового акта выполненных работ';
    public $exists = false;
    public $act;
    public $hasDuplicates = false;
    
    private ActCreationService $creationService;

    public function __construct()
    {
        $this->creationService = new ActCreationService();
    }

    public function query(Act $act): array
    {
        $this->exists = $act->exists;
        $this->act = $act;
        $step = request()->get('step', 'main_data');
        
        if ($this->exists) {
            $act->load(['tasks' => function($query) {
                $query->with([
                    'project:id,name',
                    'executor:id,name',
                    'creator:id,name',
                    'category:id,name'
                ]);
            }]);
            
            return [
                'act' => $act,
                'act_tasks' => $act->tasks,
                'step' => 'main_data',
            ];
        }
        
        $query = ['act' => $act, 'step' => $step];
        
        if ($step === 'task_selection') {
            $actData = $this->creationService->getSessionData();
            
            if (!$actData || !isset($actData['project_id'])) {
                session()->forget('act_data');
                $query['error'] = 'Данные акта утеряны или неполны';
                return $query;
            }
            
            $selectedIds = session()->get('selected_task_ids', []);
            
            $tasks = $this->creationService->getTasksForSelection(
                $actData['project_id'],
                $selectedIds,
                null
            );
            
            $this->checkForDuplicates($tasks);
            
            $query['tasks'] = $tasks;
            $query['project'] = Project::find($actData['project_id']);
            $query['act_data'] = $actData;
            $query['has_duplicates'] = $this->hasDuplicates;
        }
        
        return $query;
    }

    private function checkForDuplicates(array $tasks): void
    {
        foreach ($tasks as $task) {
            if (!empty($task['used_in_acts'])) {
                $this->hasDuplicates = true;
                break;
            }
        }
    }

    public function commandBar(): array
    {
        $step = request()->get('step', 'main_data');
        
        if ($this->exists) {
            return [
                Link::make('Назад')
                    ->icon('arrow-left')
                    ->route('platform.systems.acts'),
                    
                Button::make('Сохранить')
                    ->icon('check')
                    ->method('save')
                    ->type(Color::DARK),
                    
                Link::make('Скачать')
                    ->icon('download')
                    ->route('platform.systems.acts.download', $this->act)
                    ->type(Color::DARK),
                    
                Button::make('Удалить')
                    ->icon('trash')
                    ->method('remove')
                    ->type(Color::DARK)
                    ->confirm('Вы уверены?'),
            ];
        }
        
        if ($step === 'task_selection') {
            $buttons = [
                Button::make('Назад')
                    ->icon('arrow-left')
                    ->method('previousStep'),
            ];
            
            if ($this->hasDuplicates) {
                $buttons[] = Button::make('Сгенерировать акт')
                    ->icon('check')
                    ->method('generateAct')
                    ->type(Color::DARK)
                    ->confirm('Обнаружены задачи, которые уже используются в других актах. Вы уверены, что хотите создать акт с этими задачами?');
            } else {
                $buttons[] = Button::make('Сгенерировать акт')
                    ->icon('check')
                    ->method('generateAct')
                    ->type(Color::DARK);
            }
            
            return $buttons;
        }
        
        return [
            Link::make('Отмена')
                ->icon('close')
                ->route('platform.systems.acts'),
                
            Button::make('Далее')
                ->icon('arrow-right')
                ->method('nextStep')
                ->type(Color::DARK),
        ];
    }

    public function layout(): array
    {
        return [ActEditLayout::class];
    }

    public function nextStep(Request $request)
    {
        try {
            $validatedData = $this->creationService->validateMainData($request);
            $this->creationService->saveMainDataToSession($validatedData);
            
            session()->forget('selected_task_ids');
            
            return redirect()->route('platform.systems.acts.create', ['step' => 'task_selection']);
            
        } catch (\Exception $e) {
            Log::error('Ошибка перехода к выбору задач', [
                'error' => $e->getMessage(),
                'data' => $request->all(),
            ]);
            
            Toast::error('Ошибка: ' . $e->getMessage());
            return back()->withInput();
        }
    }

    public function previousStep()
    {
        $this->creationService->clearSessionData();
        session()->forget('selected_task_ids');
        return redirect()->route('platform.systems.acts.create', ['step' => 'main_data']);
    }

    public function selectAllTasks()
    {
        $actData = $this->creationService->getSessionData();
        
        if (!$actData || !isset($actData['project_id'])) {
            Toast::warning('Данные акта утеряны. Начните заново.');
            return redirect()->route('platform.systems.acts.create');
        }
        
        $tasks = $this->creationService->getTasksForSelection($actData['project_id'], [], null);
        
        $selectedIds = [];
        foreach ($tasks as $task) {
            $selectedIds[] = $task['id'] ?? 0;
        }
        
        session(['selected_task_ids' => $selectedIds]);
        
        Toast::info('Все задачи выбраны.');
        return redirect()->route('platform.systems.acts.create', ['step' => 'task_selection']);
    }

    public function generateAct(Request $request)
    {
        try {
            $actData = $this->creationService->getSessionData();
            
            if (!$actData) {
                Toast::warning('Данные акта утеряны. Начните заново.');
                return redirect()->route('platform.systems.acts.create');
            }
            
            $tasks = $request->input('tasks', []);
            
            $selectedTasks = [];
            $selectedTaskIds = [];
            foreach ($tasks as $task) {
                $isSelected = false;
                
                if (isset($task['selected'])) {
                    $isSelected = ($task['selected'] === '1' || 
                                  $task['selected'] === true || 
                                  $task['selected'] === 'on' || 
                                  $task['selected'] === 'true');
                }

                if ($isSelected) {
                    $taskId = $task['id'] ?? null;
                    $hours = $task['hours'] ?? 0;
                    
                    if ($taskId) {
                        $selectedTasks[$taskId] = ['hours' => (float) $hours];
                        $selectedTaskIds[] = $taskId;
                    }
                }
            }
            
            if (empty($selectedTasks)) {
                Toast::warning('Выберите хотя бы одну задачу');
                return back()->withInput();
            }
            
            $duplicates = $this->creationService->checkTaskDuplicates($selectedTaskIds, null);
            
            if (!empty($duplicates)) {
                Log::info('Создание акта с дублирующимися задачами', [
                    'act_data' => $actData,
                    'duplicates' => $duplicates,
                    'user_id' => auth()->id(),
                ]);
            }
            
            $totalHours = array_sum(array_column($selectedTasks, 'hours'));
            
            $processedTasks = [
                'selectedTasks' => $selectedTasks,
                'totalHours' => (float) $totalHours,
                'totalTasks' => count($selectedTasks),
            ];
            
            $act = $this->creationService->createAct($actData, $processedTasks);
            
            $this->creationService->clearSessionData();
            session()->forget('selected_task_ids');
            
            if (!empty($duplicates)) {
                Alert::success('Акт успешно создан. Внимание: некоторые задачи уже были использованы в других актах.');
            } else {
                Alert::success('Акт успешно создан.');
            }
            
            return redirect()->route('platform.systems.acts.edit', $act);
            
        } catch (\Exception $e) {
            Log::error('Ошибка создания акта', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            Toast::error('Ошибка создания акта: ' . $e->getMessage());
            return redirect()->route('platform.systems.acts.create', ['step' => 'main_data']);
        }
    }

    public function save(Act $act, Request $request)
    {
        try {
            $request->validate([
                'act.number' => 'required|string|unique:acts,number,' . $act->id,
                'act.date' => 'required|date',
                'act.customer' => 'required|string',
                'act.executor' => 'required|string',
            ], [
                'act.number.required' => 'Поле "Номер акта" обязательно для заполнения.',
                'act.number.unique' => 'Акт с таким номером уже существует.',
                'act.number.string' => 'Поле "Номер акта" должно быть строкой.',
                'act.date.required' => 'Поле "Дата акта" обязательно для заполнения.',
                'act.date.date' => 'Поле "Дата акта" должно быть датой.',
                'act.customer.required' => 'Поле "Заказчик" обязательно для заполнения.',
                'act.customer.string' => 'Поле "Заказчик" должно быть строкой.',
                'act.executor.required' => 'Поле "Исполнитель" обязательно для заполнения.',
                'act.executor.string' => 'Поле "Исполнитель" должно быть строкой.',
            ]);
            
            $act->fill($request->get('act'))->save();
            Alert::success('Акт успешно обновлен.');
            return redirect()->route('platform.systems.acts');
            
        } catch (\Exception $e) {
            Log::error('Ошибка сохранения акта', [
                'error' => $e->getMessage(),
                'act_id' => $act->id,
            ]);
            
            Toast::error('Ошибка сохранения: ' . $e->getMessage());
            return back()->withInput();
        }
    }

    public function remove(Act $act)
    {
        try {
            Log::info('Акт удален', [
                'id' => $act->id,
                'number' => $act->number,
                'user_id' => auth()->id(),
            ]);
            
            $act->tasks()->detach();
            $act->delete();
            
            Alert::success('Акт успешно удален.');
            return redirect()->route('platform.systems.acts');
            
        } catch (\Exception $e) {
            Log::error('Ошибка удаления акта', [
                'error' => $e->getMessage(),
                'act_id' => $act->id,
            ]);
            
            Toast::error('Ошибка удаления: ' . $e->getMessage());
            return back();
        }
    }

    public function downloadWord(Act $act)
    {
        try {
            $act->load(['tasks']);
            
            $tasksData = [];
            $totalHours = 0;
            
            foreach ($act->tasks as $task) {
                if (!$task) continue;
                
                $hours = $task->pivot->hours ?? $task->estimation_hours ?? 0;
                
                if (!is_numeric($hours)) {
                    $hours = 0;
                }
                
                $hours = (float) $hours;
                $totalHours += $hours;
                
                $tasksData[] = [
                    'name' => (string) ($task->name ?? 'Задача без названия'),
                    'hours' => $hours,
                ];
            }
            
            $data = [
                'act' => [
                    'number' => (string) $act->number,
                    'date' => $act->date,
                    'customer' => (string) $act->customer,
                    'executor' => (string) $act->executor,
                    'customer_director' => (string) ($act->customer_director ?? ''),
                    'executor_fullname' => (string) ($act->executor_fullname ?? ''),
                ],
                'tasks' => $tasksData,
                'total_hours' => $totalHours,
            ];
            
            return WordForLaravel::load('word.act', $data)
                ->download($act->number . '.docx');
                
        } catch (\Exception $e) {
            Log::error('Ошибка скачивания акта', [
                'error' => $e->getMessage(),
                'act_id' => $act->id,
                'tasks' => $act->tasks->pluck('id', 'name'),
                'trace' => $e->getTraceAsString(),
            ]);
            
            Toast::error('Ошибка генерации документа: ' . $e->getMessage());
            return back();
        }
    }
}