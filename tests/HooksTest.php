<?php

namespace Larapack\Hooks\Tests;

use Carbon\Carbon;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Larapack\Hooks\Composer;
use Larapack\Hooks\Hook;
use Larapack\Hooks\Hooks;

class HooksTest extends TestCase
{
    const COMPOSER_REPOSITORY = 'https://testing.larapack.io';

    public function setUp(): void
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
        Hooks::fakeDateTime($carbon = Carbon::createFromFormat('Y/m/d H:i:s', '2018/01/20 12:00:00'));

        $migrationDateTime = $carbon->format('Y_m_d_His');

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

        // Check stubs
        $this->assertFileExists(base_path('hooks/local-test-hook/composer.json'));
        $this->assertEquals(
            $filesystem->get(__DIR__.'/fixtures/composer.json'),
            $filesystem->get(base_path('hooks/local-test-hook/composer.json'))
        );

        $this->assertFileExists(base_path('hooks/local-test-hook/src/LocalTestHook.php'));
        $this->assertEquals(
            $filesystem->get(__DIR__.'/fixtures/src/LocalTestHook.php'),
            $filesystem->get(base_path('hooks/local-test-hook/src/LocalTestHook.php'))
        );

        $this->assertFileExists(base_path('hooks/local-test-hook/src/LocalTestHookFacade.php'));
        $this->assertEquals(
            $filesystem->get(__DIR__.'/fixtures/src/LocalTestHookFacade.php'),
            $filesystem->get(base_path('hooks/local-test-hook/src/LocalTestHookFacade.php'))
        );

        $this->assertFileExists(base_path('hooks/local-test-hook/src/LocalTestHookServiceProvider.php'));
        $this->assertEquals(
            $filesystem->get(__DIR__.'/fixtures/src/LocalTestHookServiceProvider.php'),
            $filesystem->get(base_path('hooks/local-test-hook/src/LocalTestHookServiceProvider.php'))
        );

        $this->assertFileExists(base_path('hooks/local-test-hook/resources/assets/scripts/alert.js'));
        $this->assertEquals(
            $filesystem->get(__DIR__.'/fixtures/resources/assets/scripts/alert.js'),
            $filesystem->get(base_path('hooks/local-test-hook/resources/assets/scripts/alert.js'))
        );

        $this->assertFileExists(base_path(
            'hooks/local-test-hook/resources/database/migrations/'.$migrationDateTime.'_create_local_test_hook_table.php'
        ));
        $this->assertEquals(
            $filesystem->get(__DIR__.'/fixtures/resources/database/migrations/'.$migrationDateTime.'_create_local_test_hook_table.php'),
            $filesystem->get(base_path('hooks/local-test-hook/resources/database/migrations/'.$migrationDateTime.'_create_local_test_hook_table.php'))
        );

        $this->assertFileExists(base_path(
            'hooks/local-test-hook/resources/database/seeders/LocalTestHookTableSeeder.php'
        ));
        $this->assertEquals(
            $filesystem->get(__DIR__.'/fixtures/resources/database/seeders/LocalTestHookTableSeeder.php'),
            $filesystem->get(base_path('hooks/local-test-hook/resources/database/seeders/LocalTestHookTableSeeder.php'))
        );

        $this->assertFileExists(base_path(
            'hooks/local-test-hook/resources/database/unseeders/LocalTestHookTableUnseeder.php'
        ));
        $this->assertEquals(
            $filesystem->get(__DIR__.'/fixtures/resources/database/unseeders/LocalTestHookTableUnseeder.php'),
            $filesystem->get(base_path('hooks/local-test-hook/resources/database/unseeders/LocalTestHookTableUnseeder.php'))
        );
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
            'description' => 'This is my first hook.',
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
            'description' => 'This is my first hook.',
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
            'description' => 'This is my first hook.',
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
            'description' => 'This is my first hook.',
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
            'description' => 'This is my first hook.',
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
            'description' => 'This is my first hook.',
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
            'description' => 'This is my first hook.',
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
            'description' => 'This is my first hook.',
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

    public function test_migrating_hook()
    {
        $filesystem = app(Filesystem::class);

        $this->assertFalse(Schema::hasTable('tests'));
        $this->assertMigrationHasNotRan('2018_01_19_000000_create_tests_table');

        // Install hook
        $this->artisan('hook:install', [
            'name'    => 'migrating-hook',
            'version' => 'v1.0.0',
        ]);

        $this->assertTrue(Schema::hasTable('tests'));
        $this->assertMigrationHasRan('2018_01_19_000000_create_tests_table');
    }

    public function test_installing_without_migrating_hook()
    {
        $filesystem = app(Filesystem::class);

        $this->assertFalse(Schema::hasTable('tests'));
        $this->assertMigrationHasNotRan('2018_01_19_000000_create_tests_table');

        // Install hook
        $this->artisan('hook:install', [
            'name'                => 'migrating-hook',
            'version'             => 'v1.0.0',
            '--no-migrate'        => true,
            '--no-seed'           => true,
        ]);

        $this->assertFalse(Schema::hasTable('tests'));
        $this->assertMigrationHasNotRan('2018_01_19_000000_create_tests_table');
    }

    public function test_unmigrating_hook()
    {
        $filesystem = app(Filesystem::class);

        $this->assertFalse(Schema::hasTable('tests'));
        $this->assertMigrationHasNotRan('2018_01_19_000000_create_tests_table');

        // Install hook
        $this->artisan('hook:install', [
            'name'    => 'migrating-hook',
            'version' => 'v1.0.0',
        ]);

        $this->assertTrue(Schema::hasTable('tests'));
        $this->assertMigrationHasRan('2018_01_19_000000_create_tests_table');

        // Uninstall hook
        $this->artisan('hook:uninstall', [
            'name'    => 'migrating-hook',
        ]);

        $this->assertFalse(Schema::hasTable('tests'));
        $this->assertMigrationHasNotRan('2018_01_19_000000_create_tests_table');
    }

    public function test_uninstalling_without_unmigrating_hook()
    {
        $filesystem = app(Filesystem::class);

        $this->assertFalse(Schema::hasTable('tests'));
        $this->assertMigrationHasNotRan('2018_01_19_000000_create_tests_table');

        // Install hook
        $this->artisan('hook:install', [
            'name'    => 'migrating-hook',
            'version' => 'v1.0.0',
        ]);

        $this->assertTrue(Schema::hasTable('tests'));
        $this->assertMigrationHasRan('2018_01_19_000000_create_tests_table');

        // Uninstall hook
        $this->artisan('hook:uninstall', [
            'name'                  => 'migrating-hook',
            '--no-unmigrate'        => true,
        ]);

        $this->assertTrue(Schema::hasTable('tests'));
        $this->assertMigrationHasRan('2018_01_19_000000_create_tests_table');
        $this->assertEquals(0, DB::table('tests')->count());
    }

    public function test_remigrating_hook_on_update()
    {
        $filesystem = app(Filesystem::class);

        $this->assertFalse(Schema::hasTable('tests'));
        $this->assertMigrationHasNotRan('2018_01_19_000000_create_tests_table');
        $this->assertFalse(Schema::hasTable('another_tests'));
        $this->assertMigrationHasNotRan('2018_01_19_100000_create_another_tests_table');

        // Install hook
        $this->artisan('hook:install', [
            'name'    => 'migrating-hook',
            'version' => 'v1.0.0',
        ]);

        $this->assertTrue(Schema::hasTable('tests'));
        $this->assertMigrationHasRan('2018_01_19_000000_create_tests_table');

        $this->assertFalse(Schema::hasTable('another_tests'));
        $this->assertMigrationHasNotRan('2018_01_19_100000_create_another_tests_table');

        // Install hook
        $this->artisan('hook:update', [
            'name'    => 'migrating-hook',
            'version' => 'v2.0.0',
        ]);

        $this->assertTrue(Schema::hasTable('tests'));
        $this->assertMigrationHasRan('2018_01_19_000000_create_tests_table');
        $this->assertTrue(Schema::hasTable('another_tests'));
        $this->assertMigrationHasRan('2018_01_19_100000_create_another_tests_table');
    }

    public function test_updating_without_remigrating_hook()
    {
        $filesystem = app(Filesystem::class);

        $this->assertFalse(Schema::hasTable('tests'));
        $this->assertMigrationHasNotRan('2018_01_19_000000_create_tests_table');
        $this->assertFalse(Schema::hasTable('another_tests'));
        $this->assertMigrationHasNotRan('2018_01_19_100000_create_another_tests_table');

        // Install hook
        $this->artisan('hook:install', [
            'name'    => 'migrating-hook',
            'version' => 'v1.0.0',
        ]);

        $this->assertTrue(Schema::hasTable('tests'));
        $this->assertMigrationHasRan('2018_01_19_000000_create_tests_table');

        $this->assertFalse(Schema::hasTable('another_tests'));
        $this->assertMigrationHasNotRan('2018_01_19_100000_create_another_tests_table');

        // Install hook
        $this->artisan('hook:update', [
            'name'                => 'migrating-hook',
            'version'             => 'v2.0.0',
            '--no-migrate'        => true,
            '--no-seed'           => true,
        ]);

        $this->assertTrue(Schema::hasTable('tests'));
        $this->assertMigrationHasRan('2018_01_19_000000_create_tests_table');
        $this->assertFalse(Schema::hasTable('another_tests'));
        $this->assertMigrationHasNotRan('2018_01_19_100000_create_another_tests_table');
    }

    public function test_seeding_hook()
    {
        $filesystem = app(Filesystem::class);

        $this->assertFalse(Schema::hasTable('tests'));

        // Install hook
        $this->artisan('hook:install', [
            'name'    => 'migrating-hook',
            'version' => 'v1.0.0',
        ]);

        $this->assertTrue(Schema::hasTable('tests'));
        $this->assertEquals(3, DB::table('tests')->count());
    }

    public function test_installing_without_seeding_hook()
    {
        $filesystem = app(Filesystem::class);

        $this->assertFalse(Schema::hasTable('tests'));

        // Install hook
        $this->artisan('hook:install', [
            'name'              => 'migrating-hook',
            'version'           => 'v1.0.0',
            '--no-seed'         => true,
        ]);

        $this->assertTrue(Schema::hasTable('tests'));
        $this->assertEquals(0, DB::table('tests')->count());
    }

    public function test_unseeding_hook()
    {
        $filesystem = app(Filesystem::class);

        $this->assertFalse(Schema::hasTable('tests'));

        // Install hook
        $this->artisan('hook:install', [
            'name'    => 'migrating-hook',
            'version' => 'v1.0.0',
        ]);

        $this->assertTrue(Schema::hasTable('tests'));
        $this->assertEquals(3, DB::table('tests')->count());

        // Uninstall hook
        $this->artisan('hook:uninstall', [
            'name'                  => 'migrating-hook',
            '--no-unmigrate'        => true,
        ]);

        $this->assertTrue(Schema::hasTable('tests'));
        $this->assertEquals(0, DB::table('tests')->count());
    }

    public function test_uninstalling_without_unseeding()
    {
        $filesystem = app(Filesystem::class);

        $this->assertFalse(Schema::hasTable('tests'));

        // Install hook
        $this->artisan('hook:install', [
            'name'    => 'migrating-hook',
            'version' => 'v1.0.0',
        ]);

        $this->assertTrue(Schema::hasTable('tests'));
        $this->assertEquals(3, DB::table('tests')->count());

        // Uninstall hook
        $this->artisan('hook:uninstall', [
            'name'                  => 'migrating-hook',
            '--no-unmigrate'        => true,
            '--no-unseed'           => true,
        ]);

        $this->assertTrue(Schema::hasTable('tests'));
        $this->assertEquals(3, DB::table('tests')->count());
    }

    public function test_reseeding_on_update()
    {
        $filesystem = app(Filesystem::class);

        $this->assertFalse(Schema::hasTable('tests'));

        // Install hook
        $this->artisan('hook:install', [
            'name'    => 'migrating-hook',
            'version' => 'v1.0.0',
        ]);

        $this->assertTrue(Schema::hasTable('tests'));
        $this->assertEquals(3, DB::table('tests')->count());

        // Update hook
        $this->artisan('hook:update', [
            'name'    => 'migrating-hook',
            'version' => 'v2.0.0',
        ]);

        $this->assertTrue(Schema::hasTable('tests'));
        $this->assertEquals(3, DB::table('tests')->count());
        $this->assertTrue(Schema::hasTable('another_tests'));
        $this->assertEquals(3, DB::table('another_tests')->count());
    }

    public function test_updating_without_reseeding()
    {
        $filesystem = app(Filesystem::class);

        $this->assertFalse(Schema::hasTable('tests'));

        // Install hook
        $this->artisan('hook:install', [
            'name'    => 'migrating-hook',
            'version' => 'v1.0.0',
        ]);

        $this->assertTrue(Schema::hasTable('tests'));
        $this->assertEquals(3, DB::table('tests')->count());

        // Update hook
        $this->artisan('hook:update', [
            'name'              => 'migrating-hook',
            'version'           => 'v2.0.0',
            '--no-seed'         => true,
        ]);

        $this->assertTrue(Schema::hasTable('tests'));
        $this->assertEquals(3, DB::table('tests')->count());
        $this->assertTrue(Schema::hasTable('another_tests'));
        $this->assertEquals(0, DB::table('another_tests')->count());
    }

    public function test_publishing_assets()
    {
        $filesystem = app(Filesystem::class);

        $this->assertFalse($filesystem->exists(base_path('public/vendor')));

        // Install hook
        $this->artisan('hook:install', [
            'name'    => 'migrating-hook',
            'version' => 'v1.0.0',
        ]);

        $this->assertTrue($filesystem->exists(base_path('public/vendor/migration-hook/assets')));
        $this->assertTrue($filesystem->exists(base_path('public/vendor/migration-hook/assets/script.js')));
        $this->assertEquals("alert('I am alive!');\n", $filesystem->get(
            base_path('public/vendor/migration-hook/assets/script.js')
        ));
    }

    public function test_installing_without_publishing_assets()
    {
        $filesystem = app(Filesystem::class);

        $this->assertFalse($filesystem->exists(base_path('public/vendor')));

        // Install hook
        $this->artisan('hook:install', [
            'name'                 => 'migrating-hook',
            'version'              => 'v1.0.0',
            '--no-publish'         => true,
        ]);

        $this->assertFalse($filesystem->exists(base_path('public/vendor')));
    }

    public function test_unpublishing_assets()
    {
        $filesystem = app(Filesystem::class);

        $this->assertFalse($filesystem->exists(base_path('public/vendor')));

        // Install hook
        $this->artisan('hook:install', [
            'name'    => 'migrating-hook',
            'version' => 'v1.0.0',
        ]);

        $this->assertTrue($filesystem->exists(base_path('public/vendor/migration-hook/assets')));
        $this->assertTrue($filesystem->exists(base_path('public/vendor/migration-hook/assets/script.js')));
        $this->assertEquals("alert('I am alive!');\n", $filesystem->get(
            base_path('public/vendor/migration-hook/assets/script.js')
        ));

        // Uninstall hook
        $this->artisan('hook:uninstall', [
            'name' => 'migrating-hook',
        ]);

        $this->assertFalse($filesystem->exists(base_path('public/vendor/migration-hook/assets/script.js')));
    }

    public function test_unpublishing_assets_without_removing_other_files_in_folder()
    {
        $filesystem = app(Filesystem::class);

        $this->assertFalse($filesystem->exists(base_path('public/vendor')));

        // Install hook
        $this->artisan('hook:install', [
            'name'    => 'migrating-hook',
            'version' => 'v1.0.0',
        ]);

        $this->assertTrue($filesystem->exists(base_path('public/vendor/migration-hook/assets')));
        $this->assertTrue($filesystem->exists(base_path('public/vendor/migration-hook/assets/script.js')));
        $this->assertEquals("alert('I am alive!');\n", $filesystem->get(
            base_path('public/vendor/migration-hook/assets/script.js')
        ));

        $filesystem->put(
            base_path('public/vendor/migration-hook/assets/test.js'),
            "alert('This is just a test!');\n"
        );

        // Uninstall hook
        $this->artisan('hook:uninstall', [
            'name' => 'migrating-hook',
        ]);

        $this->assertFalse($filesystem->exists(base_path('public/vendor/migration-hook/assets/script.js')));
        $this->assertTrue($filesystem->exists(base_path('public/vendor/migration-hook/assets/test.js')));
        $this->assertEquals("alert('This is just a test!');\n", $filesystem->get(
            base_path('public/vendor/migration-hook/assets/test.js')
        ));
    }

    public function test_uninstalling_without_unpublishing_assets()
    {
        $filesystem = app(Filesystem::class);

        $this->assertFalse($filesystem->exists(base_path('public/vendor')));

        // Install hook
        $this->artisan('hook:install', [
            'name'    => 'migrating-hook',
            'version' => 'v1.0.0',
        ]);

        $this->assertTrue($filesystem->exists(base_path('public/vendor/migration-hook/assets')));
        $this->assertTrue($filesystem->exists(base_path('public/vendor/migration-hook/assets/script.js')));
        $this->assertEquals("alert('I am alive!');\n", $filesystem->get(
            base_path('public/vendor/migration-hook/assets/script.js')
        ));

        // Uninstall hook
        $this->artisan('hook:uninstall', [
            'name'                   => 'migrating-hook',
            '--no-unpublish'         => true,
        ]);

        $this->assertTrue($filesystem->exists(base_path('public/vendor/migration-hook/assets')));
        $this->assertTrue($filesystem->exists(base_path('public/vendor/migration-hook/assets/script.js')));
        $this->assertEquals("alert('I am alive!');\n", $filesystem->get(
            base_path('public/vendor/migration-hook/assets/script.js')
        ));
    }

    public function test_republishing_assets_on_update()
    {
        $filesystem = app(Filesystem::class);

        $this->assertFalse($filesystem->exists(base_path('public/vendor')));

        // Install hook
        $this->artisan('hook:install', [
            'name'    => 'migrating-hook',
            'version' => 'v1.0.0',
        ]);

        $this->assertTrue($filesystem->exists(base_path('public/vendor/migration-hook/assets')));
        $this->assertTrue($filesystem->exists(base_path('public/vendor/migration-hook/assets/script.js')));
        $this->assertEquals("alert('I am alive!');\n", $filesystem->get(
            base_path('public/vendor/migration-hook/assets/script.js')
        ));

        // Update hook
        $this->artisan('hook:update', [
            'name'    => 'migrating-hook',
            'version' => 'v2.0.0',
        ]);

        $this->assertTrue($filesystem->exists(base_path('public/vendor/migration-hook/assets/script.js')));
        $this->assertTrue($filesystem->exists(base_path('public/vendor/migration-hook/assets/another.js')));
        $this->assertEquals("alert('I am still alive!');\n", $filesystem->get(
            base_path('public/vendor/migration-hook/assets/script.js')
        ));
        $this->assertEquals("alert('I am another file!');\n", $filesystem->get(
            base_path('public/vendor/migration-hook/assets/another.js')
        ));
    }

    public function test_updating_without_republishing_assets()
    {
        $filesystem = app(Filesystem::class);

        $this->assertFalse($filesystem->exists(base_path('public/vendor')));

        // Install hook
        $this->artisan('hook:install', [
            'name'    => 'migrating-hook',
            'version' => 'v1.0.0',
        ]);

        $this->assertTrue($filesystem->exists(base_path('public/vendor/migration-hook/assets')));
        $this->assertTrue($filesystem->exists(base_path('public/vendor/migration-hook/assets/script.js')));
        $this->assertEquals("alert('I am alive!');\n", $filesystem->get(
            base_path('public/vendor/migration-hook/assets/script.js')
        ));

        // Update hook
        $this->artisan('hook:update', [
            'name'                 => 'migrating-hook',
            'version'              => 'v2.0.0',
            '--no-publish'         => true,
        ]);

        $this->assertTrue($filesystem->exists(base_path('public/vendor/migration-hook/assets/script.js')));
        $this->assertFalse($filesystem->exists(base_path('public/vendor/migration-hook/assets/another.js')));
        $this->assertEquals("alert('I am alive!');\n", $filesystem->get(
            base_path('public/vendor/migration-hook/assets/script.js')
        ));
    }

    public function test_republishing_assets_on_update_with_custom_changed_files()
    {
        $filesystem = app(Filesystem::class);

        $this->assertFalse($filesystem->exists(base_path('public/vendor')));

        // Install hook
        $this->artisan('hook:install', [
            'name'    => 'migrating-hook',
            'version' => 'v1.0.0',
        ]);

        $this->assertTrue($filesystem->exists(base_path('public/vendor/migration-hook/assets')));
        $this->assertTrue($filesystem->exists(base_path('public/vendor/migration-hook/assets/script.js')));
        $this->assertEquals("alert('I am alive!');\n", $filesystem->get(
            base_path('public/vendor/migration-hook/assets/script.js')
        ));

        $filesystem->put(
            base_path('public/vendor/migration-hook/assets/script.js'),
            "alert('Am I still alive?');\n"
        );

        // Update hook
        $this->artisan('hook:update', [
            'name'    => 'migrating-hook',
            'version' => 'v2.0.0',
        ]);

        $this->assertTrue($filesystem->exists(base_path('public/vendor/migration-hook/assets/script.js')));
        $this->assertTrue($filesystem->exists(base_path('public/vendor/migration-hook/assets/another.js')));
        $this->assertEquals("alert('Am I still alive?');\n", $filesystem->get(
            base_path('public/vendor/migration-hook/assets/script.js')
        ));
        $this->assertEquals("alert('I am another file!');\n", $filesystem->get(
            base_path('public/vendor/migration-hook/assets/another.js')
        ));
    }

    public function test_force_republishing_assets_on_update_with_custom_changed_files()
    {
        $filesystem = app(Filesystem::class);

        $this->assertFalse($filesystem->exists(base_path('public/vendor')));

        // Install hook
        $this->artisan('hook:install', [
            'name'    => 'migrating-hook',
            'version' => 'v1.0.0',
        ]);

        $this->assertTrue($filesystem->exists(base_path('public/vendor/migration-hook/assets')));
        $this->assertTrue($filesystem->exists(base_path('public/vendor/migration-hook/assets/script.js')));
        $this->assertEquals("alert('I am alive!');\n", $filesystem->get(
            base_path('public/vendor/migration-hook/assets/script.js')
        ));

        $filesystem->put(
            base_path('public/vendor/migration-hook/assets/script.js'),
            "alert('Am I still alive?');\n"
        );

        // Update hook
        $this->artisan('hook:update', [
            'name'            => 'migrating-hook',
            'version'         => 'v2.0.0',
            '--force'         => true,
        ]);

        $this->assertTrue($filesystem->exists(base_path('public/vendor/migration-hook/assets/script.js')));
        $this->assertTrue($filesystem->exists(base_path('public/vendor/migration-hook/assets/another.js')));
        $this->assertEquals("alert('I am still alive!');\n", $filesystem->get(
            base_path('public/vendor/migration-hook/assets/script.js')
        ));
        $this->assertEquals("alert('I am another file!');\n", $filesystem->get(
            base_path('public/vendor/migration-hook/assets/another.js')
        ));
    }

    public function test_hook_migrating_single_file()
    {
        $filesystem = app(Filesystem::class);

        $this->assertFalse(Schema::hasTable('tests'));
        $this->assertMigrationHasNotRan('2018_01_21_000000_create_tests_table');

        // Install hook
        $this->artisan('hook:install', [
            'name'    => 'single-migrating-hook',
            'version' => 'v1.0.0',
        ]);

        $this->assertTrue(Schema::hasTable('tests'));
        $this->assertMigrationHasRan('2018_01_21_000000_create_tests_table');
        $this->assertFalse(Schema::hasTable('another_tests'));
        $this->assertMigrationHasNotRan('2018_01_21_000000_create_another_tests_table');
    }

    public function test_hook_unmigrating_single_file()
    {
        $filesystem = app(Filesystem::class);

        $this->assertFalse(Schema::hasTable('tests'));
        $this->assertMigrationHasNotRan('2018_01_21_000000_create_tests_table');

        // Install hook
        $this->artisan('hook:install', [
            'name'    => 'single-migrating-hook',
            'version' => 'v1.0.0',
        ]);

        $this->assertTrue(Schema::hasTable('tests'));
        $this->assertMigrationHasRan('2018_01_21_000000_create_tests_table');
        $this->assertFalse(Schema::hasTable('another_tests'));
        $this->assertMigrationHasNotRan('2018_01_21_000000_create_another_tests_table');

        // Uninstall hook
        $this->artisan('hook:uninstall', [
            'name'    => 'single-migrating-hook',
        ]);

        $this->assertFalse(Schema::hasTable('tests'));
        $this->assertMigrationHasNOtRan('2018_01_21_000000_create_tests_table');
        $this->assertFalse(Schema::hasTable('another_tests'));
        $this->assertMigrationHasNotRan('2018_01_21_000000_create_another_tests_table');
    }

    public function test_hook_seeding_single_file()
    {
        $filesystem = app(Filesystem::class);

        $this->assertFalse(Schema::hasTable('tests'));
        $this->assertMigrationHasNotRan('2018_01_21_000000_create_tests_table');

        // Install hook
        $this->artisan('hook:install', [
            'name'    => 'single-migrating-hook',
            'version' => 'v1.0.0',
        ]);

        $this->assertTrue(Schema::hasTable('tests'));
        $this->assertMigrationHasRan('2018_01_21_000000_create_tests_table');
        $this->assertEquals(3, DB::table('tests')->count());
        $this->assertFalse(Schema::hasTable('another_tests'));
        $this->assertMigrationHasNotRan('2018_01_21_000000_create_another_tests_table');
    }

    public function test_hook_unseeding_single_file()
    {
        $filesystem = app(Filesystem::class);

        $this->assertFalse(Schema::hasTable('tests'));
        $this->assertMigrationHasNotRan('2018_01_21_000000_create_tests_table');

        // Install hook
        $this->artisan('hook:install', [
            'name'    => 'single-migrating-hook',
            'version' => 'v1.0.0',
        ]);

        $this->assertTrue(Schema::hasTable('tests'));
        $this->assertMigrationHasRan('2018_01_21_000000_create_tests_table');
        $this->assertEquals(3, DB::table('tests')->count());
        $this->assertFalse(Schema::hasTable('another_tests'));
        $this->assertMigrationHasNotRan('2018_01_21_000000_create_another_tests_table');

        // Uninstall hook
        $this->artisan('hook:uninstall', [
            'name'           => 'single-migrating-hook',
            '--no-unmigrate' => true,
        ]);

        $this->assertTrue(Schema::hasTable('tests'));
        $this->assertMigrationHasRan('2018_01_21_000000_create_tests_table');
        $this->assertEquals(0, DB::table('tests')->count());
        $this->assertFalse(Schema::hasTable('another_tests'));
        $this->assertMigrationHasNotRan('2018_01_21_000000_create_another_tests_table');
    }

    public function test_hook_publishing_single_file()
    {
        $filesystem = app(Filesystem::class);

        $this->assertFalse($filesystem->exists(base_path('public/vendor')));

        // Install hook
        $this->artisan('hook:install', [
            'name'    => 'single-migrating-hook',
            'version' => 'v1.0.0',
        ]);

        $this->assertTrue($filesystem->exists(base_path('public/vendor/single-migrating-hook/assets/scripts')));
        $this->assertTrue($filesystem->exists(base_path('public/vendor/single-migrating-hook/assets/scripts/alert.js')));
        $this->assertEquals("alert('Test!');\n", $filesystem->get(
            base_path('public/vendor/single-migrating-hook/assets/scripts/alert.js')
        ));

        $this->assertFalse($filesystem->exists(base_path('public/vendor/single-migrating-hook/assets/scripts/another-test.js')));
    }

    public function test_hook_unpublishing_single_file()
    {
        $filesystem = app(Filesystem::class);

        $this->assertFalse($filesystem->exists(base_path('public/vendor')));

        // Install hook
        $this->artisan('hook:install', [
            'name'    => 'single-migrating-hook',
            'version' => 'v1.0.0',
        ]);

        $this->assertTrue($filesystem->exists(base_path('public/vendor/single-migrating-hook/assets/scripts')));
        $this->assertTrue($filesystem->exists(base_path('public/vendor/single-migrating-hook/assets/scripts/alert.js')));
        $this->assertEquals("alert('Test!');\n", $filesystem->get(
            base_path('public/vendor/single-migrating-hook/assets/scripts/alert.js')
        ));

        $this->assertFalse($filesystem->exists(base_path('public/vendor/single-migrating-hook/assets/scripts/another-test.js')));

        $filesystem->put(base_path('public/vendor/single-migrating-hook/assets/scripts/another-test.js'), "alert('Another test!');\n");

        // Uninstall hook
        $this->artisan('hook:uninstall', [
            'name' => 'single-migrating-hook',
        ]);

        $this->assertTrue($filesystem->exists(base_path('public/vendor/single-migrating-hook/assets/scripts')));
        $this->assertFalse($filesystem->exists(base_path('public/vendor/single-migrating-hook/assets/scripts/alert.js')));
        $this->assertTrue($filesystem->exists(base_path('public/vendor/single-migrating-hook/assets/scripts/another-test.js')));
    }

    protected function assertMigrationHasRan($name)
    {
        return $this->assertTrue(
            DB::table('migrations')->where('migration', $name)->count() == 1
        );
    }

    protected function assertMigrationHasNotRan($name)
    {
        return $this->assertFalse(
            DB::table('migrations')->where('migration', $name)->count() == 1
        );
    }
}
