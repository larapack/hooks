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

        $this->registerCommands();

        $this->publishes(
            [$configPath => config_path('hooks.php')],
            'hooks-config'
        );

        // Register Hooks system and aliases
        $this->app->singleton(Hooks::class, function ($app) {
            $filesystem = $app[Filesystem::class];
            $migrator = $app[Migrator::class];

            return new Hooks($filesystem, $migrator);
        });

        $this->app->alias(Hooks::class, 'hooks');

        // The migrator is responsible for actually running and rollback the migration
        // files in the application. We'll pass in our database connection resolver
        // so the migrator can resolve any of these connections when it needs to.
        $this->app->singleton(Migrator::class, function ($app) {
            $repository = $app['migration.repository'];

            return new Migrator($repository, $app['db'], $app['files']);
        });
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
