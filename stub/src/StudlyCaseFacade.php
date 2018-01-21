<?php

namespace StudlyCase;

use Illuminate\Support\Facades\Facade;

class StudlyCaseFacade extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return StudlyCase::class;
    }
}
