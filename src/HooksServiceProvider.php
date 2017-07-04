<?php

namespace Larapack\Hooks;

use Illuminate\Support\ServiceProvider;
use Illuminate\Foundation\AliasLoader;

class HooksServiceProvider extends ServiceProvider
{
    /**
     * Register the application services.
     */
    public function register()
    {
        // Registers resources and commands
        if ($this->app->runningInConsole()) {
            $this->registerCommands();
        }

        // Register Hooks system and aliases
        $this->app->singleton(Hooks::class, Hooks::class);
        $this->app->alias(Hooks::class, 'hooks');

        // Register hook providers
        $this->registerHookProviders();
    }

    /**
     * Register Hook Service Providers.
     */
    public function registerHookProviders()
    {
        // load only the enabled hooks
        $hooks = app('hooks')->hooks()->where('enabled', true);
        $loader = AliasLoader::getInstance();

        foreach ($hooks as $hook) {
            // load providers
            foreach ($hook->getProviders() as $provider) {
                $this->app->register($provider);
            }

            // set aliases
            foreach ($hook->getAliases() as $alias => $class) {
                $loader->alias($alias, $class);
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
        $this->commands(Commands\SetupCommand::class);
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
}
