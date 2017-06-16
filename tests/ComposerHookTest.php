<?php

namespace Larapack\Hooks\Tests;

use Illuminate\Filesystem\Filesystem;
use Larapack\Hooks\Hook;
use Larapack\Hooks\Hooks;

class ComposerHookTest extends TestCase
{
    public function setup()
    {
        parent::setup();
        Hooks::setRemote('https://testing.larapack.io');
    }

    public function test_composer_file_is_setup()
    {
        $filesystem = app(Filesystem::class);

        // Install hook
        $this->artisan('hook:install', [
            'name' => 'composer-hook',
        ]);

        // Check that hooks folder and composer.json file does exists
        $this->assertTrue($filesystem->isDirectory(base_path('hooks')));
        $this->assertTrue($filesystem->exists(base_path('hooks/composer.json')));

        // Check that the hook folder exists and have a composer file
        $this->assertTrue($filesystem->isDirectory(base_path('hooks/composer-hook')));
        $this->assertTrue($filesystem->exists(base_path('hooks/composer-hook/composer.json')));

        // Check that project composer file is correct
        $composer = json_decode($filesystem->get(base_path('composer.json')), true);
        $this->assertNotNull($composer);
        $this->assertTrue(isset($composer['extra']));
        $this->assertTrue(isset($composer['extra']['merge-plugin']));
        $this->assertEquals(
            [
                'require' => [
                    'hooks/composer.json',
                ],
                'recurse'           => true,
                'replace'           => false,
                'ignore-duplicates' => false,
                'merge-dev'         => true,
                'merge-extra'       => false,
                'merge-extra-deep'  => false,
                'merge-scripts'     => false,
            ],
            $composer['extra']['merge-plugin']
        );
        /*
        $this->assertTrue(isset($composer['repositories']));
        $this->assertEquals(
            [
                [
                    'type' => 'path',
                    'url' => 'hooks',
                ]
            ],
            $composer['repositories']
        );
        $this->assertTrue(isset($composer['require']));
        $this->assertEquals(
            [
                'laravel/framework' => '~5.0',
                'hooks' => '*',
            ],
            $composer['require']
        );
        */

        // Test that hooks composer file is correct
        $composer = json_decode($filesystem->get(base_path('hooks/composer.json')), true);
        $this->assertNotNull($composer);
        $this->assertTrue(isset($composer['extra']));
        $this->assertTrue(isset($composer['extra']['merge-plugin']));
        $this->assertEquals(
            [
                'require' => [
                    'composer-hook/composer.json',
                ],
                'recurse'           => true,
                'replace'           => false,
                'ignore-duplicates' => false,
                'merge-dev'         => true,
                'merge-extra'       => false,
                'merge-extra-deep'  => false,
                'merge-scripts'     => false,
            ],
            $composer['extra']['merge-plugin']
        );
        /*
        $this->assertTrue(isset($composer['repositories']));
        $this->assertEquals(
            [
                [
                    'type' => 'path',
                    'url' => 'hooks/composer-hook',
                ]
            ],
            $composer['repositories']
        );
        $this->assertTrue(isset($composer['require']));
        $this->assertEquals(
            ['marktopper/composer-hook' => '*'],
            $composer['require']
        );
        $this->assertTrue(isset($composer['minimum-stability']));
        $this->assertEquals('dev', $composer['minimum-stability']);
        */

        // Check that the hook details is correct
        $hook = app('hooks')->hook('composer-hook');
        $expect = [
            'name'         => 'composer-hook',
            'enabled'      => false,
            'composerName' => 'marktopper/composer-hook',
        ];
        foreach ($expect as $key => $value) {
            $this->assertEquals($value, $hook->$key);
        }
    }

    public function test_using_composer_require()
    {
        $filesystem = app(Filesystem::class);

        // Install hook
        $this->artisan('hook:install', [
            'name'     => 'composer-hook',
            '--enable' => true,
        ]);

        $this->assertEquals(
            [
                /*
                'name' => 'hooks',
                'version' => '1.0.0',
                'repositories' => [
                    [
                        'type' => 'path',
                        'url' => 'hooks/composer-hook',
                    ],
                ],
                'require' => [
                    'marktopper/composer-hook' => '*'
                ],
                */
                'autoload' => [
                    'classmap' => [
                        'hooks/composer-hook',
                    ],
                ],
                //'minimum-stability' => 'dev',
                'extra' => [
                    'merge-plugin' => [
                        'require'           => ['composer-hook/composer.json'],
                        'recurse'           => true,
                        'replace'           => false,
                        'ignore-duplicates' => false,
                        'merge-dev'         => true,
                        'merge-extra'       => false,
                        'merge-extra-deep'  => false,
                        'merge-scripts'     => false,
                    ],
                ],
                'require' => [
                    'marktopper/composer-hook-dependency-1' => '*',
                ],
            ],
            json_decode($filesystem->get(base_path('hooks/composer.json')), true)
        );

        // Check that the hook details is correct
        $hook = app('hooks')->hook('composer-hook');
        $expect = [
            'name'         => 'composer-hook',
            'enabled'      => true,
            'composerName' => 'marktopper/composer-hook',
        ];
        foreach ($expect as $key => $value) {
            $this->assertEquals($value, $hook->$key);
        }
    }

    public function test_that_dependency_is_downloaded()
    {
        $filesystem = app(Filesystem::class);

        // Install hook
        $this->artisan('hook:install', [
            'name'     => 'composer-hook',
            '--enable' => true,
        ]);

        dump(
            $filesystem->get(base_path('composer.json')),
            $filesystem->get(base_path('hooks/composer.json')),
            $filesystem->get(base_path('hooks/composer-hook/composer.json')),
            base_path('hooks')
        );
        die();
        echo app(Hooks::class)->composerCommand('install');
        dump(scandir(base_path('vendor')));
        dump(scandir(base_path('vendor/marktopper')));

        $this->assertTrue($filesystem->isDirectory(base_path('vendor/marktopper/composer-hook-dependency-1')));
    }

    public function test_hook_with_weird_name()
    {
    }

    public function test_hook_without_name()
    {
    }

    public function test_autoloading_without_composer_file()
    {
    }

    public function test_autoloading_with_composer_file_without_autoload_section()
    {
    }

    public function test_using_custom_autoloading_method_defined_in_composer_file()
    {
    }

    public function test_dependencies_are_fixed_on_update()
    {
    }

    public function test_autoloading_are_fixed_on_update()
    {
    }
}
