<?php

namespace Larapack\Hooks\Commands;

use Larapack\Hooks\Hooks;
use Illuminate\Console\Command;

class UninstallCommand extends Command
{
    protected $signature = 'hook:uninstall {name} {--remove}';

    protected $description = 'Uninstall a hook';

    protected $hooks;

    public function __construct(Hooks $hooks)
    {
        $this->hooks = $hooks;

        parent::__construct();
    }

    public function fire()
    {
        $name = $this->argument('name');

        $this->hooks->uninstall($name, $this->option('remove'));

        $this->info("Hook [{$name}] have been uninstalled.");
    }
}