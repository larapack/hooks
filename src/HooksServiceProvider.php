<?php

namespace Larapack\Hooks;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\AliasLoader;
use Illuminate\Support\ServiceProvider;

class HooksServiceProvider extends ServiceProvider
{
    /**
     * Register the application services.
     */
    public function register()
    {
        $configPath = dirname(__DIR__).'/publishable/config/hooks.php';

        $this->mergeConfigFrom($configPath, 'hooks');

        if (!config('hooks.enabled', true)) {
            return;
        }

        // Registers resources and commands
        if ($this->app->runningInConsole()) {
            $this->registerCommands();

            $this->publishes(
                [$configPath => config_path('hooks.php')],
                'hooks-config'
            );
        }

        // Register Hooks system and aliases
        $this->app->singleton(Hooks::class, function ($app) {
            $filesystem = $app[Filesystem::class];
            $migrator = $app['migrator'];

            return new Hooks($filesystem, $migrator);
        });
        $this->app->alias(Hooks::class, 'hooks');
    }

    /**
     * Register Hook Service Providers.
     */
    public function registerHookProviders()
    {
        // load only the enabled hooks
        $hooks = $this->app['hooks']->hooks()->where('enabled', true);
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
        if (!config('hooks.enabled', true)) {
            return;
        }

        // Register hook providers
        $this->registerHookProviders();
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
