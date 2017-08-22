<?php

namespace Larapack\Hooks\Commands;

use Illuminate\Console\Command;
use Larapack\Hooks\Hooks;

class InstallCommand extends Command
{
    protected $signature = 'hook:install {name} {version?} {--enable}';

    protected $description = 'Download and install a hook from remote https://larapack.io';

    protected $hooks;

    public function __construct(Hooks $hooks)
    {
        $this->hooks = $hooks;

        parent::__construct();
    }

    public function fire()
    {
        return $this->handle();
    }

    public function handle()
    {
        $name = $this->argument('name');

        $this->hooks->install($name, $this->argument('version'));

        if ($this->option('enable')) {
            $this->hooks->enable($name);

            $this->info("Hook [{$name}] have been installed and enabled.");
        } else {
            $this->info("Hook [{$name}] have been installed.");
        }
    }
}
