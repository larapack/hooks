<?php

namespace Larapack\Hooks\Commands;

use Illuminate\Console\Command;
use Larapack\Hooks\Hooks;

class MakeCommand extends Command
{
    protected $signature = 'hook:make {name}';

    protected $description = 'Make a hook';

    protected $hooks;

    public function __construct(Hooks $hooks)
    {
        $this->hooks = $hooks;

        parent::__construct();
    }

    public function fire()
    {
        $name = $this->argument('name');

        $this->hooks->make($name);

        $this->info("Hook [{$name}] have been made.");
    }
}
