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
        $name = $this->argument('name');

        $this->hooks->disable($name);

        $this->info("Hook [{$name}] have been disabled.");
    }
}
