<?php

namespace Docudoodle;

use Illuminate\Support\ServiceProvider;
use Docudoodle\Commands\GenerateDocsCommand;

class DocudoodleServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateDocsCommand::class,
            ]);
            
            $this->publishes([
                __DIR__.'/../config/docudoodle.php' => config_path('docudoodle.php'),
            ], 'docudoodle-config');
        }
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/docudoodle.php', 'docudoodle'
        );
    }
}