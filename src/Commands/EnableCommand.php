<?php

namespace Larapack\Hooks\Commands;

use Larapack\Hooks\Hooks;
use Illuminate\Console\Command;

class EnableCommand extends Command
{
    protected $signature = 'hook:enable {name}';

    protected $description = 'Enable a hook';

    protected $hooks;

    public function __construct(Hooks $hooks)
    {
        $this->hooks = $hooks;

        parent::__construct();
    }

    public function fire()
    {
        $name = $this->argument('name');

        $this->hooks->enable($name);

        $this->info("Hook [{$name}] have been enabled.");
    }
}