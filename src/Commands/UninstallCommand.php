<?php

namespace Larapack\Hooks\Commands;

use Illuminate\Console\Command;
use Larapack\Hooks\Hooks;

class UninstallCommand extends Command
{
    protected $signature = 'hook:uninstall {name} {--delete} {--no-unmigrate} {--no-unseed} {--no-unpublish}';

    protected $description = 'Uninstall a hook';

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

        $this->hooks->uninstall(
            $name,
            $this->option('delete'),
            !$this->option('no-unmigrate'),
            !$this->option('no-unseed'),
            !$this->option('no-unpublish')
        );

        $this->info("Hook [{$name}] has been uninstalled.");
    }
}
