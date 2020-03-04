<?php

namespace Larapack\Hooks\Tests;

use Illuminate\Filesystem\Filesystem;
use Larapack\Hooks\Composer;
use Larapack\Hooks\Hooks;

class TestCase extends \Orchestra\Testbench\TestCase
{
    protected function getPackageProviders($app)
    {
        return ['Larapack\Hooks\HooksServiceProvider'];
    }

    /**
     * Setup the test environment.
     */
    public function setUp(): void
    {
        parent::setUp();

        $filesystem = app(Filesystem::class);

        // Cleanup old hooks before testing
        $filesystem->deleteDirectory(base_path('hooks'));

        // Cleanup published files
        $filesystem->deleteDirectory(base_path('public/vendor'));

        // Clear old hooks
        $hook = app(Hooks::class);
        $hook->readJsonFile();

        // Delete testbench's fixures tests folder
        $filesystem->deleteDirectory(base_path('tests'));

        // Create tests folder
        $filesystem->makeDirectory(base_path('tests'));
        file_put_contents(base_path('tests/TestCase.php'), '<?php ');

        // Cleanup Composer
        $composer = new Composer();
        $composer->set('repositories', []);
        $composer->set('minimum-stability', 'stable');
        $composer->set('require', [
            'laravel/framework' => $composer->get('require.laravel/framework'),
        ]);
        $composer->save();
        $filesystem->delete(base_path('composer.lock'));

        // Cleanup vendor
        $filesystem->deleteDirectory(base_path('vendor'));
        $filesystem->makeDirectory(base_path('vendor'));

        // Setup Hook repository
        $this->artisan('hook:setup', [
            '--url' => env('HOOKS_COMPOSER_REPOSITORY', static::COMPOSER_REPOSITORY),
        ]);

        // Reload JSON files
        app(Hooks::class)->readJsonFile();

        // Migrate
        $this->artisan('migrate');
    }

    public function tearDown(): void
    {
        // Cleanup old hooks before testing
        app(Filesystem::class)->deleteDirectory(base_path('hooks'));

        parent::tearDown();
    }

    /**
     * Define environment setup.
     *
     * @param \Illuminate\Foundation\Application $app
     *
     * @return void
     */
    protected function getEnvironmentSetUp($app)
    {
        // Setup default database to use sqlite :memory:
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);
    }
}
