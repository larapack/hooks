<?php

namespace Larapack\Hooks\Commands;

use Larapack\Hooks\Composer;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

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
        $composer = new Composer(base_path('composer.json'));

        $composer->setRepository(static::REPOSITORY_NAME, [
            'type' => 'composer',
            'url' => $this->option('url'),
        ]);

        if (starts_with($this->option('url'), 'http://')) {
            $composer->setConfig('secure-http', false);
        }

        $composer->save();

        $this->info('Hooks are now ready to use! Go ahead and try to "composer require test-hook"');
    }
}
