<?php

namespace Larapack\Hooks;

use Illuminate\Support\ServiceProvider;

class HooksServiceProvider extends ServiceProvider
{
    /**
     * Register the application services.
     */
    public function register()
    {
        // Registers resources and commands
        if ($this->app->runningInConsole()) {
            $this->registerPublishableResources();
            $this->registerCommands();
        }

        // Load default configurations
        $this->mergeConfigFrom(
            dirname(__DIR__).'/publishable/config/hooks.php', 'hooks'
        );

        // Register Hooks system and aliases
        $this->app->singleton(Hooks::class, Hooks::class);
        $this->app->alias(Hooks::class, 'hooks');

        // Register script variables
        $this->registerScriptVariables();

        // Register downloaders
        $this->app->bind('hooks.downloaders.github', Downloaders\GithubDownloader::class);

        // Register hook providers
        $this->registerHookProviders();
    }

    /**
     * Register Hook Script Variables.
     */
    protected function registerScriptVariables()
    {
        $scriptVariables = [
            'HOOKS' => base_path('hooks'),
        ];
        foreach ($scriptVariables as $key => $value) {
            Hooks::addScriptVariable($key, $value);
        }
    }

    /**
     * Register Hook Service Providers.
     */
    public function registerHookProviders()
    {
        // load only the enabled hooks
        $hooks = app('hooks')->hooks()->where('enabled', true);

        // load providers
        foreach ($hooks as $hook) {
            if (isset($hook['provider'])) {
                $this->app->register($hook['provider']);
            }

            if (isset($hook['providers'])) {
                foreach ($hook['providers'] as $provider) {
                    $this->app->register($provider);
                }
            }
        }
    }

    /**
     * Bootstrap the application services.
     */
    public function boot()
    {
        //
    }

    /**
     * Register commands.
     */
    protected function registerCommands()
    {
        $this->commands(Commands\MakeCommand::class);
        $this->commands(Commands\InstallCommand::class);
        $this->commands(Commands\UninstallCommand::class);
        $this->commands(Commands\UpdateCommand::class);
        $this->commands(Commands\CheckCommand::class);
        $this->commands(Commands\EnableCommand::class);
        $this->commands(Commands\DisableCommand::class);
        $this->commands(Commands\InfoCommand::class);
        $this->commands(Commands\ListCommand::class);
    }

    /**
     * Register the publishable files.
     */
    private function registerPublishableResources()
    {
        $basePath = dirname(__DIR__);
        $publishable = [
            'config' => [
                "$basePath/publishable/config/hooks.php" => config_path('hooks.php'),
            ],
        ];

        foreach ($publishable as $group => $paths) {
            $this->publishes($paths, $group);
        }
    }
}
