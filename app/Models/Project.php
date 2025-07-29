<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Orchid\Screen\AsSource;

class Project extends Model
{
    use HasFactory, AsSource;

    protected $fillable = [
        'name',
    ];

    /**
     * Связь многие-ко-многим с клиентами
     */
    public function clients()
    {
        return $this->belongsToMany(User::class, 'client_project');
    }

    /**
     * Связь с задачами проекта
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }
}
