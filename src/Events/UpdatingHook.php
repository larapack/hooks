<?php

namespace Larapack\Hooks\Events;

use Larapack\Hooks\Hook;

class UpdatingHook
{
    public $hook;

    /**
     * Create a new event instance.
     *
     * @param \Larapack\Hooks\Hook $hook
     */
    public function __construct(Hook $hook)
    {
        $this->hook = $hook;
    }
}
