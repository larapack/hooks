<?php

namespace Larapack\Hooks\Tests;

use Illuminate\Filesystem\Filesystem;
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
    public function setUp()
    {
        parent::setUp();

        // Cleanup old hooks before testing
        app(Filesystem::class)->deleteDirectory(base_path('hooks'));

        // Clear old hooks
        $hook = app(Hooks::class);
        $hook->readJsonFile();

        // Delete testbench's fixures tests folder
        app(Filesystem::class)->deleteDirectory(base_path('tests'));

        // Create tests folder
        app(Filesystem::class)->makeDirectory(base_path('tests'));
        file_put_contents(base_path('tests/TestCase.php'), '<?php ');
    }

    public function tearDown()
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

class PreparedHook
{
    public function __construct($data)
    {
        $this->data = $data;
    }
}
