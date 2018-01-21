<?php

namespace LocalTestHook;

use Illuminate\Support\Facades\Facade;

class LocalTestHookFacade extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return LocalTestHook::class;
    }
}
