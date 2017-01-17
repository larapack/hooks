<?php

namespace Larapack\Hooks\Events;

class UninstalledHook
{
    public $hook;

    /**
     * Create a new event instance.
     *
     * @param string $hook
     */
    public function __construct($hook)
    {
        $this->hook = $hook;
    }
}
