<?php

namespace App\Providers;

use App\Services\QdrantCollectionsService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Enregistrement du service QdrantCollectionsService comme singleton
        $this->app->singleton(QdrantCollectionsService::class, function ($app) {
            return new QdrantCollectionsService;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
