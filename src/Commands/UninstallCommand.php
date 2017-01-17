<?php

namespace Larapack\Hooks\Commands;

use Illuminate\Console\Command;
use Larapack\Hooks\Hooks;

class UninstallCommand extends Command
{
    protected $signature = 'hook:uninstall {name} {--keep}';

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

        $this->hooks->uninstall($name, $this->option('keep'));

        $this->info("Hook [{$name}] have been uninstalled.");
    }
}
