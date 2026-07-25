<?php

namespace App\Services;

use App\CoreLayer\Enums\TaskStatusEnum;
use App\Models\Act;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ActService
{
    public function suggestNumber(?int $projectId = null): string
    {
        $year = now()->format('Y');
        $prefix = 'АКТ-' . $year . '-';

        $last = Act::query()
            ->where('number', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->value('number');

        $seq = 1;
        if ($last && preg_match('/(\d+)$/', (string) $last, $m)) {
            $seq = ((int) $m[1]) + 1;
        }

        return $prefix . str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
    }

    public function defaultCustomer(?Project $project): string
    {
        if (!$project) {
            return '';
        }

        $client = $project->clients()->orderBy('name')->first();

        return $client?->displayName() ?? $client?->name ?? '';
    }

    public function defaultExecutor(): string
    {
        return (string) (auth()->user()?->displayName() ?? auth()->user()?->name ?? '');
    }

    /**
     * Задачи для композера акта.
     *
     * @return list<array{
     *   id:int,title:string,status:string,status_label:string,
     *   estimation_hours:float,hours_spent:float,hours:float,
     *   executor:string,selected:bool,used_in_acts:list<array{id:int,number:string}>,
     *   has_duplicates:bool
     * }>
     */
    public function tasksForComposer(int $projectId, ?Act $act = null): array
    {
        $selectedMap = [];
        if ($act?->exists) {
            foreach ($act->tasks as $task) {
                $selectedMap[(int) $task->id] = (float) ($task->pivot->hours ?? 0);
            }
        }

        $currentActId = $act?->exists ? (int) $act->id : null;

        return Task::query()
            ->where('project_id', $projectId)
            ->with([
                'executor:id,name,position',
                'acts' => function ($q) use ($currentActId) {
                    $q->when($currentActId, fn ($qq) => $qq->where('acts.id', '!=', $currentActId))
                        ->select('acts.id', 'acts.number');
                },
            ])
            ->orderByDesc('updated_at')
            ->get()
            ->map(function (Task $task) use ($selectedMap) {
                $spent = round((float) ($task->hours_spent ?? 0), 2);
                $estimate = round((float) ($task->estimation_hours ?? 0), 2);
                $defaultHours = $spent > 0 ? $spent : max(0, $estimate);

                $selected = array_key_exists((int) $task->id, $selectedMap);
                $hours = $selected ? max(0, $selectedMap[(int) $task->id]) : $defaultHours;

                $usedInActs = $task->acts->map(fn (Act $a) => [
                    'id' => (int) $a->id,
                    'number' => (string) $a->number,
                ])->values()->all();

                $status = (string) $task->status;

                return [
                    'id' => (int) $task->id,
                    'title' => (string) $task->name,
                    'status' => $status,
                    'status_label' => TaskStatusEnum::tryFrom($status)?->label() ?? $status,
                    'estimation_hours' => $estimate,
                    'hours_spent' => $spent,
                    'hours' => round($hours, 2),
                    'executor' => $task->executor?->displayName() ?? '—',
                    'selected' => $selected,
                    'used_in_acts' => $usedInActs,
                    'has_duplicates' => $usedInActs !== [],
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $lines  request lines[taskId] => [selected, hours]
     * @return array{selectedTasks: array<int, array{hours: float}>, totalHours: float, totalTasks: int, taskIds: list<int>}
     */
    public function parseLines(array $lines): array
    {
        $selectedTasks = [];

        foreach ($lines as $taskId => $row) {
            $taskId = (int) $taskId;
            if ($taskId <= 0 || !is_array($row)) {
                continue;
            }

            $raw = $row['selected'] ?? false;
            $isSelected = $raw === true
                || $raw === 1
                || $raw === '1'
                || $raw === 'on'
                || $raw === 'true';

            if (!$isSelected) {
                continue;
            }

            $hours = round((float) str_replace(',', '.', (string) ($row['hours'] ?? 0)), 2);
            if ($hours < 0) {
                $hours = 0;
            }

            $selectedTasks[$taskId] = ['hours' => $hours];
        }

        if ($selectedTasks === []) {
            throw ValidationException::withMessages([
                'lines' => 'Выберите хотя бы одну задачу и укажите часы.',
            ]);
        }

        $totalHours = round(array_sum(array_column($selectedTasks, 'hours')), 2);

        return [
            'selectedTasks' => $selectedTasks,
            'totalHours' => $totalHours,
            'totalTasks' => count($selectedTasks),
            'taskIds' => array_keys($selectedTasks),
        ];
    }

    /**
     * @param  array{number:string,date:string,customer:string,executor:string,info?:string,project_id:int}  $header
     * @param  array{selectedTasks: array<int, array{hours: float}>, totalHours: float, totalTasks: int}  $lines
     */
    public function save(?Act $act, array $header, array $lines): Act
    {
        return DB::transaction(function () use ($act, $header, $lines) {
            $model = $act?->exists ? $act : new Act();

            $model->fill([
                'project_id' => (int) $header['project_id'],
                'number' => $header['number'],
                'date' => $header['date'],
                'customer' => $header['customer'],
                'executor' => $header['executor'],
                'info' => $header['info'] ?? '',
                'total_hours' => $lines['totalHours'],
                'total_tasks' => $lines['totalTasks'],
                'status' => 'generated',
                'generated_at' => $model->generated_at ?? now(),
            ]);
            $model->save();

            $model->tasks()->sync($lines['selectedTasks']);

            return $model->fresh(['tasks', 'project']);
        });
    }

    /**
     * @return list<array{id:int,number:string,date:string}>
     */
    public function duplicateWarnings(array $taskIds, ?int $exceptActId = null): array
    {
        $out = [];
        foreach ($this->checkDuplicates($taskIds, $exceptActId) as $acts) {
            foreach ($acts as $act) {
                $out[$act['id']] = $act;
            }
        }

        return array_values($out);
    }

    /**
     * @return array<int, list<array{id:int,number:string,date:string}>>
     */
    public function checkDuplicates(array $taskIds, ?int $exceptActId = null): array
    {
        $duplicates = [];

        foreach ($taskIds as $taskId) {
            $task = Task::query()
                ->with(['acts' => function ($q) use ($exceptActId) {
                    $q->when($exceptActId, fn ($qq) => $qq->where('acts.id', '!=', $exceptActId));
                }])
                ->find($taskId);

            if ($task && $task->acts->isNotEmpty()) {
                $duplicates[(int) $taskId] = $task->acts->map(fn (Act $act) => [
                    'id' => (int) $act->id,
                    'number' => (string) $act->number,
                    'date' => $act->date?->format('d.m.Y') ?? '',
                ])->all();
            }
        }

        return $duplicates;
    }
}
