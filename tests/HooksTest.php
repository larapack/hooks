<?php

namespace Larapack\Hooks\Tests;

use Illuminate\Filesystem\Filesystem;
use Larapack\Hooks\Hook;
use Larapack\Hooks\Hooks;

class HooksTest extends TestCase
{
    const COMPOSER_REPOSITORY = 'http://larapack.dev';
    //const COMPOSER_REPOSITORY = 'https://testing.larapack.io';

    public function setUp()
    {
        parent::setUp();

        Hooks::setRemote(static::COMPOSER_REPOSITORY);

        print_r("\nSETUP!\n");

        $filesystem = app(Filesystem::class);

        // Cleanup Composer
        $composer = json_decode($filesystem->get(base_path('composer.json')), true);
        $composer['require'] = [
            'laravel/framework' => $composer['require']['laravel/framework'],
        ];
        $filesystem->put(base_path('composer.json'), json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
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
    }

    public function test_repository_set()
    {
        $filesystem = app(Filesystem::class);

        $composer = json_decode($filesystem->get(base_path('composer.json')), true);

        $this->assertTrue(isset($composer['repositories']));
        $this->assertEquals([
            'hooks' => [
                'url'  => static::COMPOSER_REPOSITORY,
                'type' => 'composer',
            ],
        ], $composer['repositories']);
    }

    public function test_install_hook_from_github()
    {
        $filesystem = app(Filesystem::class);

        // Install hook
        $this->artisan('hook:install', [
            'name' => 'composer-github-hook',
        ]);

        // Check that hooks folder does exists
        $this->assertTrue($filesystem->isDirectory(base_path('hooks')));

        // Check that the hook folder exists
        $this->assertTrue($filesystem->isDirectory(base_path('vendor/composer-github-hook')));

        // Check that the hook details is correct
        $hook = app('hooks')->hook('composer-github-hook');
        $expect = [
            'name'        => 'composer-github-hook',
            'version'     => 'dev-master', //'v0.0.2',// TODO: !!!
            'description' => 'This is a hook',
            //'type'        => 'github', // TODO: !!!
            'enabled'     => false,
        ];
        foreach ($expect as $key => $value) {
            $this->assertEquals($value, $hook->$key);
        }
    }

    public function test_install_hook_from_github_with_enable_option()
    {
        // Install hook and enable hook
        $this->artisan('hook:install', [
            'name'     => 'composer-github-hook',
            '--enable' => true,
        ]);

        // Check that hook is enabled
        $hook = app('hooks')->hook('composer-github-hook');
        $this->assertTrue($hook->enabled);
    }

    public function test_making_local_hook()
    {
        $filesystem = app(Filesystem::class);

        // Make hook
        $this->artisan('hook:make', [
            'name' => 'local-test-hook',
        ]);

        // Check that hooks folder does exists
        $this->assertTrue($filesystem->isDirectory(base_path('hooks')));

        // Check that the hook folder exists
        $this->assertTrue($filesystem->isDirectory(base_path('hooks/local-test-hook')));

        // Check that hook is not yet installed
        $this->assertCount(0, app('hooks')->hooks()->all());
    }

    public function test_installing_local_hook()
    {
        // Make hook
        $this->artisan('hook:make', [
            'name' => 'local-test-hook',
        ]);

        // Install hook
        $this->artisan('hook:install', [
            'name' => 'local-test-hook',
        ]);

        // Check that the hook details is correct
        $hook = app('hooks')->hook('local-test-hook');
        $expect = [
            'name'        => 'local-test-hook',
            'version'     => null,
            'description' => 'This is a hook.',
            'type'        => 'local',
            'enabled'     => false,
        ];
        foreach ($expect as $key => $value) {
            $this->assertEquals($value, $hook->$key);
        }
    }

    public function test_enabling_hook()
    {
        $filesystem = app(Filesystem::class);

        // Make hook
        $this->artisan('hook:make', [
            'name' => 'local-test-hook',
        ]);

        // Check that hooks folder does exists
        $this->assertTrue($filesystem->isDirectory(base_path('hooks')));

        // Check that the hook folder exists
        $this->assertTrue($filesystem->isDirectory(base_path('hooks/local-test-hook')));

        // Check that hook is not yet installed
        $hooks = app('hooks')->hooks()->all();
        $this->assertCount(0, $hooks);

        // Install hook
        $this->artisan('hook:install', [
            'name' => 'local-test-hook',
        ]);

        // Check that the hook details is correct
        $hook = app('hooks')->hook('local-test-hook');
        $expect = [
            'name'        => 'local-test-hook',
            'version'     => null,
            'description' => 'This is a hook.',
            'type'        => 'local',
            'enabled'     => false,
        ];
        foreach ($expect as $key => $value) {
            $this->assertEquals($value, $hook->$key);
        }

        // Enable hook
        $this->artisan('hook:enable', [
            'name' => 'local-test-hook',
        ]);

        // Check that the hook details is correct
        $hook = app('hooks')->hook('local-test-hook');
        $expect = [
            'name'        => 'local-test-hook',
            'version'     => null,
            'description' => 'This is a hook.',
            'type'        => 'local',
            'enabled'     => true,
        ];
        foreach ($expect as $key => $value) {
            $this->assertEquals($value, $hook->$key);
        }
    }

    public function test_disabling_hook()
    {
        $filesystem = app(Filesystem::class);

        // Make hook
        $this->artisan('hook:make', [
            'name' => 'local-test-hook',
        ]);

        // Check that hooks folder does exists
        $this->assertTrue($filesystem->isDirectory(base_path('hooks')));

        // Check that the hook folder exists
        $this->assertTrue($filesystem->isDirectory(base_path('hooks/local-test-hook')));

        // Check that hook is not yet installed
        $hooks = app('hooks')->hooks()->all();
        $this->assertCount(0, $hooks);

        // Install hook
        $this->artisan('hook:install', [
            'name' => 'local-test-hook',
        ]);

        // Check that the hook details is correct
        $hook = app('hooks')->hook('local-test-hook');
        $expect = [
            'name'        => 'local-test-hook',
            'version'     => null,
            'description' => 'This is a hook.',
            'type'        => 'local',
            'enabled'     => false,
        ];
        foreach ($expect as $key => $value) {
            $this->assertEquals($value, $hook->$key);
        }

        // Enable hook
        $this->artisan('hook:enable', [
            'name' => 'local-test-hook',
        ]);

        // Check that the hook details is correct
        $hook = app('hooks')->hook('local-test-hook');
        $expect = [
            'name'        => 'local-test-hook',
            'version'     => null,
            'description' => 'This is a hook.',
            'type'        => 'local',
            'enabled'     => true,
        ];
        foreach ($expect as $key => $value) {
            $this->assertEquals($value, $hook->$key);
        }

        // Disable hook
        $this->artisan('hook:disable', [
            'name' => 'local-test-hook',
        ]);

        // Check that the hook details is correct
        $hook = app('hooks')->hook('local-test-hook');
        $expect = [
            'name'        => 'local-test-hook',
            'version'     => null,
            'description' => 'This is a hook.',
            'type'        => 'local',
            'enabled'     => false,
        ];
        foreach ($expect as $key => $value) {
            $this->assertEquals($value, $hook->$key);
        }
    }

    public function test_uninstall_hook()
    {
        $filesystem = app(Filesystem::class);

        // Make hook
        $this->artisan('hook:make', [
            'name' => 'local-test-hook',
        ]);

        // Check that hooks folder does exists
        $this->assertTrue($filesystem->isDirectory(base_path('hooks')));

        // Check that the hook folder exists
        $this->assertTrue($filesystem->isDirectory(base_path('hooks/local-test-hook')));

        // Check that hook is not yet installed
        $hooks = app('hooks')->hooks()->all();
        $this->assertCount(0, $hooks);

        // Install hook
        $this->artisan('hook:install', [
            'name' => 'local-test-hook',
        ]);

        // Check that the hook details is correct
        $hook = app('hooks')->hook('local-test-hook');
        $expect = [
            'name'        => 'local-test-hook',
            'version'     => null,
            'description' => 'This is a hook.',
            'type'        => 'local',
            'enabled'     => false,
        ];
        foreach ($expect as $key => $value) {
            $this->assertEquals($value, $hook->$key);
        }

        // Uninstall hook
        $this->artisan('hook:uninstall', [
            'name'   => 'local-test-hook',
            '--keep' => true,
        ]);

        // Check that the hook folder still exists
        $this->assertTrue($filesystem->isDirectory(base_path('hooks/local-test-hook')));
    }

    public function test_uninstall_hook_without_keep_parameter()
    {
        $filesystem = app(Filesystem::class);

        // Make hook
        $this->artisan('hook:make', [
            'name' => 'local-test-hook',
        ]);

        // Check that hooks folder does exists
        $this->assertTrue($filesystem->isDirectory(base_path('hooks')));

        // Check that the hook folder exists
        $this->assertTrue($filesystem->isDirectory(base_path('hooks/local-test-hook')));

        // Check that hook is not yet installed
        $hooks = app('hooks')->hooks()->all();
        $this->assertCount(0, $hooks);

        // Install hook
        $this->artisan('hook:install', [
            'name' => 'local-test-hook',
        ]);

        // Check that the hook details is correct
        $hook = app('hooks')->hook('local-test-hook');
        $expect = [
            'name'        => 'local-test-hook',
            'version'     => null,
            'description' => 'This is a hook.',
            'type'        => 'local',
            'enabled'     => false,
        ];
        foreach ($expect as $key => $value) {
            $this->assertEquals($value, $hook->$key);
        }

        // Uninstall hook
        $this->artisan('hook:uninstall', [
            'name' => 'local-test-hook',
        ]);

        // Check that the hook no longer folder exists
        $this->assertFalse($filesystem->isDirectory(base_path('hooks/local-test-hook')));
    }

    public function test_installing_specific_version()
    {
        $filesystem = app(Filesystem::class);

        // Install hook
        $this->artisan('hook:install', [
            'name'    => 'composer-github-hook',
            'version' => 'v0.0.1',
        ]);

        // Check that hooks folder does exists
        $this->assertTrue($filesystem->isDirectory(base_path('hooks')));

        // Check that the hook folder exists
        $this->assertTrue($filesystem->isDirectory(base_path('hooks/composer-github-hook')));

        // Check that the hook details is correct
        $hook = app('hooks')->hook('composer-github-hook');
        $expect = [
            'name'        => 'composer-github-hook',
            'version'     => 'v0.0.1',
            'description' => 'This is a hook.',
            'type'        => 'github',
            'enabled'     => false,
        ];
        foreach ($expect as $key => $value) {
            $this->assertEquals($value, $hook->$key);
        }

        // Check that version is correct
        $this->assertEquals('v0.0.1', trim($filesystem->get(base_path('hooks/composer-github-hook/version'))));
    }

    public function test_hook_scripts_are_called()
    {
        $filesystem = app(Filesystem::class);

        // check install log does not already exists.
        $this->assertFalse($filesystem->exists(base_path('hooks/composer-github-hook/scripts/install.log')));

        // Install hook
        $this->artisan('hook:install', [
            'name'    => 'composer-github-hook',
            'version' => 'v0.0.1',
        ]);

        // Check that install scripts where runned on the hook
        $this->assertTrue($filesystem->exists(base_path('hooks/composer-github-hook/scripts/install.log')));
        $this->assertEquals('installed', $filesystem->get(base_path('hooks/composer-github-hook/scripts/install.log')));

        // check update log does not already exists.
        $this->assertFalse($filesystem->exists(base_path('hooks/composer-github-hook/scripts/update.log')));

        // Update hook
        $this->artisan('hook:update', [
            'name' => 'composer-github-hook',
        ]);

        // Check that update scripts where runned on the hook
        $this->assertTrue($filesystem->exists(base_path('hooks/composer-github-hook/scripts/update.log')));
        $this->assertEquals('updated', $filesystem->get(base_path('hooks/composer-github-hook/scripts/update.log')));

        // check enable log does not already exists.
        $this->assertFalse($filesystem->exists(base_path('hooks/composer-github-hook/scripts/enable.log')));

        // Enable hook
        $this->artisan('hook:enable', [
            'name' => 'composer-github-hook',
        ]);

        // Check that enable scripts where runned on the hook
        $this->assertTrue($filesystem->exists(base_path('hooks/composer-github-hook/scripts/enable.log')));
        $this->assertEquals('enabled', $filesystem->get(base_path('hooks/composer-github-hook/scripts/enable.log')));

        // check disable log does not already exists.
        $this->assertFalse($filesystem->exists(base_path('hooks/composer-github-hook/scripts/disable.log')));

        // Disable hook
        $this->artisan('hook:disable', [
            'name' => 'composer-github-hook',
        ]);

        // Check that disable scripts where runned on the hook
        $this->assertTrue($filesystem->exists(base_path('hooks/composer-github-hook/scripts/disable.log')));
        $this->assertEquals('disabled', $filesystem->get(base_path('hooks/composer-github-hook/scripts/disable.log')));

        // check uninstall log does not already exists.
        $this->assertFalse($filesystem->exists(base_path('hooks/composer-github-hook/scripts/uninstall.log')));

        // Uninstall hook
        $this->artisan('hook:uninstall', [
            'name'   => 'composer-github-hook',
            '--keep' => true,
        ]);

        // Check that uninstall scripts where runned on the hook
        $this->assertTrue($filesystem->exists(base_path('hooks/composer-github-hook/scripts/enable.log')));
        $this->assertEquals('enabled', $filesystem->get(base_path('hooks/composer-github-hook/scripts/enable.log')));
    }

    public function test_update_hook()
    {
        // Install hook
        $this->artisan('hook:install', [
            'name'    => 'composer-github-hook',
            'version' => 'v0.0.1',
        ]);

        // Update hook
        $this->artisan('hook:update', [
            'name' => 'composer-github-hook',
        ]);

        // Check version is correct
        $hook = app('hooks')->hook('composer-github-hook');
        $this->assertEquals('v0.0.2', $hook->version);
    }

    public function test_updating_to_specific_version()
    {
        // Install hook
        $this->artisan('hook:install', [
            'name'    => 'composer-github-hook',
            'version' => 'v0.0.1',
        ]);

        // Update hook
        $this->artisan('hook:update', [
            'name'    => 'composer-github-hook',
            'version' => 'master',
        ]);

        // Check version is correct
        $hook = app('hooks')->hook('composer-github-hook');
        $this->assertEquals('master', $hook->version);
    }

    public function test_checking_hooks_for_updates()
    {
        // Install hook
        $this->artisan('hook:install', [
            'name'    => 'composer-github-hook',
            'version' => 'v0.0.1',
        ]);

        $this->artisan('hook:check');

        $hooks = app('hooks')->hooks()->filter(function (Hook $hook) {
            return $hook->hasUpdateAvailable();
        });

        $this->assertCount(1, $hooks);
    }
}
