<?php

namespace Larapack\Hooks\Commands;

use Illuminate\Console\Command;
use Larapack\Hooks\Hooks;

class InfoCommand extends Command
{
    protected $signature = 'hook:info {name}';

    protected $description = 'Get information on a hook';

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

        $hook = $this->hooks->hooks()->where('name', $name)->first();

        if (is_null($hook)) {
            return $this->error("Hook [{$name}] not found.");
        }

        $this->comment($name);
        $this->line("  <info>Name:</info>     {$name}");
        $this->line('  <info>Status:</info>   '.($hook['enabled'] ? 'Enabled' : 'Disabled'));
        $this->line('  <info>Version:</info>  '.(!is_null($hook['version']) ? $hook['version'] : 'None'));
    }
}
