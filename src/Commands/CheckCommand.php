<?php

namespace Larapack\Hooks\Commands;

use Illuminate\Console\Command;
use Larapack\Hooks\Hooks;

class CheckCommand extends Command
{
    protected $signature = 'hook:check';

    protected $description = 'Check for updates and show hooks that can be updated';

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
        $hooks = $this->hooks->checkForUpdates();

        $count = $hooks->count();

        $this->info(($count == 1 ? '1 update' : $count.' updates').' available.');

        foreach ($hooks as $hook) {
            $this->comment($hook->name.' '.$hook->version);
        }
    }
}
