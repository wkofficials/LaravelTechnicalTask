<?php

namespace App\Providers;

use App\Datasets\ContactsDataset;
use App\Datasets\DatasetRegistry;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Scoped: reset for every request and every queued job.
        $this->app->scoped(CurrentCompany::class);

        $this->app->singleton(DatasetRegistry::class, fn () => new DatasetRegistry([
            new ContactsDataset(),
        ]));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // The admin area (AdminLTE) is Bootstrap-based, not Tailwind.
        Paginator::useBootstrapFive();

        // {dataset} in URLs: only registered datasets exist; anything else is a 404.
        Route::bind('dataset', fn (string $key) => $this->app->make(DatasetRegistry::class)->getOrFail($key));
    }
}
