<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Orchid\Screen\AsSource;

class TaskAttachment extends Model
{
    use HasFactory, AsSource;
    protected $fillable = [
        'task_id',
        'user_id',
        'original_name',
        'path',
        'mime_type',
        'size'
    ];

    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
