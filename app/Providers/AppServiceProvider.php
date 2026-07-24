<?php

namespace App\Providers;

use App\Services\ProjectContext;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ProjectContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer([
            'platform::dashboard',
            'platform::partials.search',
            'partials.project-switcher',
            'partials.project-context-banner',
        ], function ($view) {
            if (!auth()->check()) {
                $view->with([
                    'availableProjects' => collect(),
                    'activeProject' => null,
                    'activeProjectId' => null,
                ]);

                return;
            }

            $context = app(ProjectContext::class);

            $view->with([
                'availableProjects' => $context->availableProjects(),
                'activeProject' => $context->project(),
                'activeProjectId' => $context->id(),
            ]);
        });
    }
}
