<?php

namespace Larapack\Hooks\Events;

class UpdatesAvailableForHook
{
    public $hook;

    /**
     * Create a new event instance.
     *
     * @param string $hook
     * @param string $version
     */
    public function __construct($hook, $version)
    {
        $this->hook = $hook;
    }
}
