<?php

namespace Larapack\Hooks\Tests;

use Larapack\Hooks\Hooks;
use Larapack\Hooks\Composer;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

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

        // Remove repository section from composer file.
        $composer = new Composer(base_path('composer.json'));
        $composer->unset('repositories');
        $composer->save();
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

    /**
     * Get the composer command for the environment.
     *
     * @return string
     */
    protected function findComposer()
    {
        if (file_exists(getcwd().'/composer.phar')) {
            return '"'.PHP_BINARY.'" '.getcwd().'/composer.phar';
        }

        return 'composer';
    }

    public function runComposerCommand($command)
    {
        $composer = $this->findComposer();

        $process = new Process($composer.' '.$command);
        $process->setWorkingDirectory(base_path())->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        return $process;
    }
}

class PreparedHook
{
    public function __construct($data)
    {
        $this->data = $data;
    }
}
