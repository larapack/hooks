<?php

namespace Larapack\Hooks\Commands;

use Larapack\Hooks\Hooks;
use Illuminate\Console\Command;

class ListCommand extends Command
{
    protected $signature = 'hook:list';

    protected $description = 'List installed hooks';

    protected $hooks;

    public function __construct(Hooks $hooks)
    {
        $this->hooks = $hooks;

        parent::__construct();
    }

    public function fire()
    {
        $this->table(['Name', 'Status'], $this->hooks->hooks()->transform(function ($hook) {
            return [
                'name' => $hook['name'],
                'enabled' => $hook['enabled'] ? 'Enabled' : 'Disabled',
            ];
        }));
    }
}