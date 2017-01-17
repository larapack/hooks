<?php

namespace Larapack\Hooks;

use Illuminate\Support\Facades\Facade;

class HooksFacade extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return Hooks::class;
    }
}
