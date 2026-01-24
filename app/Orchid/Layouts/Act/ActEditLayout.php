<?php

namespace App\Orchid\Layouts\Act;

use App\Orchid\Layouts\Act\Steps\MainDataStep;
use App\Orchid\Layouts\Act\Steps\TaskSelectionStep;
use App\Orchid\Layouts\Act\Steps\EditActStep;
use Orchid\Screen\Layouts\Rows;

class ActEditLayout extends Rows
{
    protected function fields(): iterable
    {
        $step = request()->get('step', 'main_data');
        $act = $this->query->get('act');
        
        if ($act->exists) {
            $actTasks = $this->query->get('act_tasks', []);
            return EditActStep::fields($act, $actTasks);
        }
        
        return match($step) {
            'main_data' => MainDataStep::fields(),
            'task_selection' => TaskSelectionStep::fields(
                $this->query->get('tasks', []),
                $this->query->get('project'),
                $this->query->get('act_data'),
                $this->query->get('has_duplicates', false)
            ),
            default => [],
        };
    }
}