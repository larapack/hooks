<?php

namespace Larapack\Hooks\Commands;

use Illuminate\Console\Command;
use Larapack\Hooks\Hooks;

class UpdateCommand extends Command
{
    protected $signature = 'hook:update {name} {version?}';

    protected $description = 'Update a hook';

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

        $hooks = $this->hooks->hooks();

        $version = $this->argument('version');

        $hook = $hooks->where('name', $name)->first();

        if ($this->hooks->update($name, $version)) {
            return $this->info("Hook [{$name}] have been updated!");
        }

        return $this->info('Nothing to update.');
    }
}
