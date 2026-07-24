<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Orchid\Screen\AsSource;

class Act extends Model
{
    use AsSource;
    
    protected $fillable = [
        'project_id',
        'number',
        'date',
        'customer',
        'executor',
        'info',
        'total_hours',
        'total_tasks',
        'status',
        'generated_at',
    ];
    
    protected $casts = [
        'date' => 'date',
        'generated_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
    
    public function tasks()
    {
        return $this->belongsToMany(Task::class, 'act_task')
            ->withPivot('hours')
            ->withTimestamps();
    }
}