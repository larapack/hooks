<?php

namespace Larapack\Hooks\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Larapack\Hooks\Composer;
use Larapack\Hooks\Events\Setup;
use Larapack\Hooks\HooksServiceProvider;

class SetupCommand extends Command
{
    const REPOSITORY_NAME = 'hooks';

    protected $signature = 'hook:setup {--url=https://larapack.io}';

    protected $description = 'Prepare Composer for using Hooks.';

    protected $filesystem;

    public function __construct(Filesystem $filesystem)
    {
        $this->filesystem = $filesystem;

        parent::__construct();
    }

    public function fire()
    {
        return $this->handle();
    }

    public function handle()
    {
        $composer = new Composer(base_path('composer.json'));

        $composer->addRepository(static::REPOSITORY_NAME, [
            'type' => 'composer',
            'url'  => $this->option('url'),
        ]);

        if (starts_with($this->option('url'), 'http://')) {
            $composer->addConfig('secure-http', false);
        }

        $composer->save();

        $this->call('vendor:publish', ['--provider' => HooksServiceProvider::class]);

        $this->info('Hooks are now ready to use! Go ahead and try to "php artisan hook:install test-hook"');

        event(new Setup());
    }
}
