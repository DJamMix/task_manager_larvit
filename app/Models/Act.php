<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Orchid\Screen\AsSource;

class Act extends Model
{
    use AsSource;
    
    protected $fillable = [
        'number',
        'date',
        'customer',
        'executor',
        'info',
        'total_hours',
        'total_tasks',
    ];
    
    protected $casts = [
        'date' => 'date',
    ];
    
    public function tasks()
    {
        return $this->belongsToMany(Task::class, 'act_task')
            ->withPivot('hours')
            ->withTimestamps();
    }
}