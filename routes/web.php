<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/test-task-search', function() {
    $userId = 6; // Используем ID из вашего индекса
    $query = 'проблему'; // Слово из ваших задач
    
    echo "=== TASK SEARCH DEBUG ===<br>";
    echo "User ID: " . $userId . "<br>";
    echo "Search query: " . $query . "<br>";
    echo "Authenticated user ID: " . auth()->id() . "<br><br>";
    
    // 1. Проверим Scout без фильтров
    try {
        $scoutAllResults = \App\Models\Task::search($query)->get();
        echo "<strong>Scout ALL results:</strong> " . $scoutAllResults->count() . "<br>";
        foreach ($scoutAllResults as $task) {
            echo "- ID: " . $task->id . " | " . $task->name . " | executor_id: " . $task->executor_id . "<br>";
        }
        echo "<br>";
        
        // 2. Проверим Scout с фильтром
        $scoutFilteredResults = \App\Models\Task::search($query)
            ->where('executor_id', $userId)
            ->get();
            
        echo "<strong>Scout FILTERED results (executor_id = $userId):</strong> " . $scoutFilteredResults->count() . "<br>";
        foreach ($scoutFilteredResults as $task) {
            echo "- ID: " . $task->id . " | " . $task->name . " | executor_id: " . $task->executor_id . "<br>";
        }
        echo "<br>";
        
        // 3. Проверим через Presenter
        $task = \App\Models\Task::first();
        if ($task) {
            $presenter = $task->presenter();
            $presenterResults = $presenter->searchQuery($query)->get();
            echo "<strong>Presenter results:</strong> " . $presenterResults->count() . "<br>";
            foreach ($presenterResults as $result) {
                echo "- ID: " . $result->id . " | " . $result->name . " | executor_id: " . $result->executor_id . "<br>";
            }
        }
        
    } catch (\Exception $e) {
        echo "<strong>Scout error:</strong> " . $e->getMessage() . "<br>";
    }
    
    dd('Debug complete');
});