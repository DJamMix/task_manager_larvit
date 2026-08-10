<?php

namespace Database\Seeders;

use App\Services\WorkflowService;
use Illuminate\Database\Seeder;

class TrackerWorkflowSeeder extends Seeder
{
    public function run(): void
    {
        app(WorkflowService::class)->bootstrapDefaults();
    }
}
