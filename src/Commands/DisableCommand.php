<?php

namespace Larapack\Hooks\Commands;

use Illuminate\Console\Command;
use Larapack\Hooks\Hooks;

class DisableCommand extends Command
{
    protected $signature = 'hook:disable {name}';

    protected $description = 'Disable a hook';

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

        $this->hooks->disable($name);

        $this->info("Hook [{$name}] has been disabled.");
    }
}
