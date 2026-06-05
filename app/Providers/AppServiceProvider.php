<?php

namespace App\Providers;

use App\Models\AuditLog;
use App\Observers\GlobalAuditObserver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);
        Vite::prefetch(concurrency: 3);
        $this->registerGlobalAuditObservers();
    }

    private function registerGlobalAuditObservers(): void
    {
        $modelsPath = app_path('Models');

        foreach (File::files($modelsPath) as $file) {
            $className = 'App\\Models\\' . $file->getFilenameWithoutExtension();

            if (!class_exists($className)) {
                continue;
            }

            if (!is_subclass_of($className, Model::class)) {
                continue;
            }

            if ($className === AuditLog::class) {
                continue;
            }

            $className::observe(GlobalAuditObserver::class);
        }
    }
}

