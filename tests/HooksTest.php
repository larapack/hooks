<?php

namespace Larapack\Hooks\Tests;

use Illuminate\Filesystem\Filesystem;
use Larapack\Hooks\Composer;
use Larapack\Hooks\Hook;
use Larapack\Hooks\Hooks;

class HooksTest extends TestCase
{
    const COMPOSER_REPOSITORY = 'https://testing.larapack.io';

    public function setUp()
    {
        // Set Hooks environment
        Hooks::setMemoryLimit('5G');
        Hooks::setRemote(static::COMPOSER_REPOSITORY);

        // Setup parent
        parent::setUp();
    }

    public function test_repository_set()
    {
        $filesystem = app(Filesystem::class);
        $composer = new Composer();

        $this->assertTrue($composer->has('repositories'));
        $this->assertEquals([
            'hooks' => [
                'url'  => static::COMPOSER_REPOSITORY,
                'type' => 'composer',
            ],
        ], $composer->get('repositories'));
    }

    public function test_install_hook_from_github()
    {
        $filesystem = app(Filesystem::class);

        // Install hook
        $this->artisan('hook:install', [
            'name' => 'composer-github-hook',
        ]);

        // Check that hooks folder does exists
        $this->assertDirectoryExists(base_path('hooks'));

        // Check that the hook folder exists
        $this->assertDirectoryExists(base_path('vendor/composer-github-hook'));

        // Check that the hook details is correct
        $hook = app('hooks')->hook('composer-github-hook');
        $expect = [
            'name'        => 'composer-github-hook',
            'version'     => 'v1.0.1',
            'description' => 'This is a hook',
            'enabled'     => false,
        ];
        foreach ($expect as $key => $value) {
            $this->assertEquals($value, $hook->$key);
        }

        $this->assertFalse($hook->isLocal());
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
        $this->assertDirectoryExists(base_path('hooks'));

        // Check that the hook folder exists
        $this->assertDirectoryExists(base_path('hooks/local-test-hook'));

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
            'enabled'     => false,
        ];
        foreach ($expect as $key => $value) {
            $this->assertEquals($value, $hook->$key);
        }

        $this->assertTrue($hook->isLocal());
    }

    public function test_enabling_hook()
    {
        $filesystem = app(Filesystem::class);

        // Make hook
        $this->artisan('hook:make', [
            'name' => 'local-test-hook',
        ]);

        // Check that hooks folder does exists
        $this->assertDirectoryExists(base_path('hooks'));

        // Check that the hook folder exists
        $this->assertDirectoryExists(base_path('hooks/local-test-hook'));

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
            'enabled'     => false,
        ];
        foreach ($expect as $key => $value) {
            $this->assertEquals($value, $hook->$key);
        }

        $this->assertTrue($hook->isLocal());

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
            'enabled'     => true,
        ];
        foreach ($expect as $key => $value) {
            $this->assertEquals($value, $hook->$key);
        }

        $this->assertTrue($hook->isLocal());
    }

    public function test_disabling_hook()
    {
        $filesystem = app(Filesystem::class);

        // Make hook
        $this->artisan('hook:make', [
            'name' => 'local-test-hook',
        ]);

        // Check that hooks folder does exists
        $this->assertDirectoryExists(base_path('hooks'));

        // Check that the hook folder exists
        $this->assertDirectoryExists(base_path('hooks/local-test-hook'));

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
            'enabled'     => false,
        ];
        foreach ($expect as $key => $value) {
            $this->assertEquals($value, $hook->$key);
        }

        $this->assertTrue($hook->isLocal());

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
            'enabled'     => true,
        ];
        foreach ($expect as $key => $value) {
            $this->assertEquals($value, $hook->$key);
        }

        $this->assertTrue($hook->isLocal());

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
            'enabled'     => false,
        ];
        foreach ($expect as $key => $value) {
            $this->assertEquals($value, $hook->$key);
        }

        $this->assertTrue($hook->isLocal());
    }

    public function test_uninstall_hook()
    {
        $filesystem = app(Filesystem::class);

        // Make hook
        $this->artisan('hook:make', [
            'name' => 'local-test-hook',
        ]);

        // Check that hooks folder does exists
        $this->assertDirectoryExists(base_path('hooks'));

        // Check that the hook folder exists
        $this->assertDirectoryExists(base_path('hooks/local-test-hook'));

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
            'enabled'     => false,
        ];
        foreach ($expect as $key => $value) {
            $this->assertEquals($value, $hook->$key);
        }

        $this->assertTrue($hook->isLocal());

        // Uninstall hook
        $this->artisan('hook:uninstall', [
            'name'     => 'local-test-hook',
            '--delete' => true,
        ]);

        // Check that the hook folder exists
        $this->assertDirectoryNotExists(base_path('hooks/local-test-hook'));
    }

    public function test_uninstall_hook_without_delete_parameter()
    {
        $filesystem = app(Filesystem::class);

        // Make hook
        $this->artisan('hook:make', [
            'name' => 'local-test-hook',
        ]);

        // Check that hooks folder does exists
        $this->assertDirectoryExists(base_path('hooks'));

        // Check that the hook folder exists
        $this->assertDirectoryExists(base_path('hooks/local-test-hook'));

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
            'enabled'     => false,
        ];
        foreach ($expect as $key => $value) {
            $this->assertEquals($value, $hook->$key);
        }

        $this->assertTrue($hook->isLocal());

        // Uninstall hook
        $this->artisan('hook:uninstall', [
            'name' => 'local-test-hook',
        ]);

        // Check that the hook folder exists
        $this->assertDirectoryExists(base_path('hooks/local-test-hook'));
    }

    public function test_installing_specific_version()
    {
        $filesystem = app(Filesystem::class);

        // Install hook
        $this->artisan('hook:install', [
            'name'    => 'composer-github-hook',
            'version' => 'v1.0.0',
        ]);

        // Check that the hook folder exists
        $this->assertDirectoryExists(base_path('vendor/composer-github-hook'));

        // Check that the hook details is correct
        $hook = app('hooks')->hook('composer-github-hook');
        $expect = [
            'name'        => 'composer-github-hook',
            'version'     => 'v1.0.0',
            'description' => 'This is a hook',
            'enabled'     => false,
        ];
        foreach ($expect as $key => $value) {
            $this->assertEquals($value, $hook->$key);
        }

        $this->assertFalse($hook->isLocal());

        // Check that version is correct
        $this->assertEquals('v1.0.0', trim($filesystem->get(base_path('vendor/composer-github-hook/version'))));
    }

    public function test_update_hook()
    {
        $filesystem = app(Filesystem::class);

        // Install hook
        $this->artisan('hook:install', [
            'name'    => 'composer-github-hook',
            'version' => 'v1.0.0',
        ]);

        Hooks::useVersionWildcardOnUpdate();

        // Update hook
        $this->artisan('hook:update', [
            'name' => 'composer-github-hook',
        ]);

        // Check version is correct
        $hook = app('hooks')->hook('composer-github-hook');
        $this->assertEquals('v1.0.1', $hook->version);
    }

    public function test_updating_to_specific_version()
    {
        // Install hook
        $this->artisan('hook:install', [
            'name'    => 'composer-github-hook',
            'version' => 'v1.0.0',
        ]);

        // Update hook
        $this->artisan('hook:update', [
            'name'    => 'composer-github-hook',
            'version' => 'v1.0.1',
        ]);

        // Check version is correct
        $hook = app('hooks')->hook('composer-github-hook');
        $this->assertEquals('v1.0.1', $hook->version);
    }

    public function test_checking_hooks_for_updates()
    {
        // Install hook
        $this->artisan('hook:install', [
            'name'    => 'composer-github-hook',
            'version' => 'v1.0.0',
        ]);

        $this->artisan('hook:check');

        $hooks = app('hooks')->hooks()->filter(function (Hook $hook) {
            return $hook->outdated();
        });

        $this->assertCount(1, $hooks);
    }

    public function test_provider_is_not_loaded_when_diabled()
    {
        // Install hook
        $this->artisan('hook:install', [
            'name' => 'composer-github-hook',
        ]);

        // Check that enabled
        $hook = app('hooks')->hook('composer-github-hook');
        $this->assertFalse($hook->enabled);

        // Require files
        require_once base_path('vendor/composer-github-hook/src/ComposerGithubHookServiceProvider.php');

        // Reload service providers
        app(\Larapack\Hooks\HooksServiceProvider::class, [
            'app' => app(),
        ])->registerHookProviders();

        // Check if service provider has run
        $this->assertFalse(\ComposerGithubHook\ComposerGithubHookServiceProvider::$isBooted);
        $this->assertFalse(\ComposerGithubHook\ComposerGithubHookServiceProvider::$isRegistered);
    }

    public function test_alias_is_not_loaded_when_disabled()
    {
        // Install hook
        $this->artisan('hook:install', [
            'name' => 'composer-github-hook',
        ]);

        // Check that enabled
        $hook = app('hooks')->hook('composer-github-hook');
        $this->assertFalse($hook->enabled);

        // Require files
        require_once base_path('vendor/composer-github-hook/src/ComposerGithubHookServiceProvider.php');
        require_once base_path('vendor/composer-github-hook/src/TestAlias.php');

        // Reload service providers
        app(\Larapack\Hooks\HooksServiceProvider::class, [
            'app' => app(),
        ])->registerHookProviders();

        // Test if alias exists
        $this->assertFalse(class_exists(\Test::class));
    }

    public function test_provider_is_loaded_when_enabled()
    {
        // Install hook
        $this->artisan('hook:install', [
            'name'     => 'composer-github-hook',
            '--enable' => true,
        ]);

        // Check that enabled
        $hook = app('hooks')->hook('composer-github-hook');
        $this->assertTrue($hook->enabled);

        // Require files
        require_once base_path('vendor/composer-github-hook/src/ComposerGithubHookServiceProvider.php');

        // Reload service providers
        app(\Larapack\Hooks\HooksServiceProvider::class, [
            'app' => app(),
        ])->registerHookProviders();

        // Check if service provider has run
        $this->assertTrue(\ComposerGithubHook\ComposerGithubHookServiceProvider::$isBooted);
        $this->assertTrue(\ComposerGithubHook\ComposerGithubHookServiceProvider::$isRegistered);
    }

    public function test_alias_is_loaded_when_enabled()
    {
        // Install hook
        $this->artisan('hook:install', [
            'name'     => 'composer-github-hook',
            '--enable' => true,
        ]);

        // Check that enabled
        $hook = app('hooks')->hook('composer-github-hook');
        $this->assertTrue($hook->enabled);

        // Require files
        require_once base_path('vendor/composer-github-hook/src/ComposerGithubHookServiceProvider.php');
        require_once base_path('vendor/composer-github-hook/src/TestAlias.php');

        // Reload service providers
        app(\Larapack\Hooks\HooksServiceProvider::class, [
            'app' => app(),
        ])->registerHookProviders();

        // Test if alias exists and works
        $this->assertTrue(class_exists(\Test::class));
        $this->assertEquals('bar', \Test::foo());
    }

    public function test_dependencies_are_downloaded()
    {
        $filesystem = app(Filesystem::class);

        // Make sure dependency not already exists
        $this->assertDirectoryNotExists(base_path('vendor/marktopper/composer-hook-dependency-1'));

        // Install hook
        $this->artisan('hook:install', [
            'name' => 'composer-github-hook',
        ]);

        // Make sure dependency is now downloaded
        $this->assertDirectoryExists(base_path('vendor/marktopper/composer-hook-dependency-1'));
    }

    public function test_updating_updates_dependencies()
    {
        $filesystem = app(Filesystem::class);

        // Make sure dependency not already exists
        $this->assertDirectoryNotExists(base_path('vendor/marktopper/composer-hook-dependency-1'));
        $this->assertDirectoryNotExists(base_path('vendor/marktopper/composer-hook-dependency-2'));

        // Install hook
        $this->artisan('hook:install', [
            'name'    => 'composer-github-hook',
            'version' => 'v1.0.0',
        ]);

        // Make sure dependency is now downloaded
        $this->assertDirectoryExists(base_path('vendor/marktopper/composer-hook-dependency-1'));
        $this->assertDirectoryNotExists(base_path('vendor/marktopper/composer-hook-dependency-2'));

        Hooks::useVersionWildcardOnUpdate();

        // Update hook
        $this->artisan('hook:update', [
            'name' => 'composer-github-hook',
        ]);

        // Make sure dependency is now downloaded
        $this->assertDirectoryExists(base_path('vendor/marktopper/composer-hook-dependency-1'));
        $this->assertDirectoryExists(base_path('vendor/marktopper/composer-hook-dependency-2'));
    }

    // TODO: Test that if a hook requires another hook, that hook should be loaded as well
}
