<?php

namespace Larapack\Hooks\Commands;

use Larapack\Hooks\Hooks;
use Illuminate\Console\Command;

class UpdateCommand extends Command
{
    protected $signature = 'hook:update {name?} {version?}';

    protected $description = 'Update all or specific hooks';

    protected $hooks;

    public function __construct(Hooks $hooks)
    {
        $this->hooks = $hooks;

        parent::__construct();
    }

    public function fire()
    {
        $name = $this->argument('name');

        $hooks = $this->hooks->hooks();

        $version = $this->argument('version');

        if (!is_null($name)) {
            $hooks = $hooks->where('name', $name);

            if (!is_null($version)) {
                if ($this->hooks->update($name, $version)) {
                    return $this->info("Hook [{$name}] have been updated to version [{$version}].");
                }

                return $this->info('Nothing to update.');
            }
        }

        $hooks = $this->hooks->checkForUpdates($hooks);

        $updated = [];

        foreach ($hooks as $hook) {
            if ($this->hooks->update($hook->name)) {
                $updated[] = $hook;
            }
        }

        $count = count($updated);

        if ($count == 0) {
            return $this->info('Nothing to update');
        }

        $this->info($count . ' ' . ($count == 1 ? 'hook' : 'hooks') . ' updated.');

        foreach ($updated as $hook) {
            $this->comment(" -> {$hook->name} {$hook->version}");
        }
    }
}