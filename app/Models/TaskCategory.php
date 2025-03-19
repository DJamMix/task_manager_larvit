<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaskCategory extends Model
{
    protected $fillable = [
        'name',
    ];

    /**
     * Связь с задачами, которые принадлежат этой категории.
     */
    public function tasks()
    {
        return $this->hasMany(Task::class, 'task_category_id');
    }
}
