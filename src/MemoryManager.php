<?php

namespace Larapack\Hooks;

abstract class MemoryManager
{
    protected static $memoryLimit = null;
    protected static $memoryLimitSet = false;

    protected function memoryInBytes($value)
    {
        $unit = strtolower(substr($value, -1, 1));
        $value = (int) $value;

        switch ($unit) {
            case 'g':
                $value *= 1024;
                // no break (cumulative multiplier)
            case 'm':
                $value *= 1024;
                // no break (cumulative multiplier)
            case 'k':
                $value *= 1024;
        }

        return $value;
    }

    public static function setMemoryLimit($memoryLimit)
    {
        static::$memoryLimit = $memoryLimit;

        if (static::$memoryLimitSet) {
            app(static::class)->prepareMemoryLimit();
        }
    }

    public static function getMemoryLimit()
    {
        return static::$memoryLimit;
    }

    public function prepareMemoryLimit()
    {
        if (!function_exists('ini_set')) {
            return;
        }

        $memoryLimit = ini_get('memory_limit');

        // Increase memory_limit if it is lower than 1.5GB
        if ($memoryLimit != -1 && $this->memoryInBytes($memoryLimit) < 1024 * 1024 * 1536) {
            $memoryLimit = '1536M';
        }

        // Increases memory_limit if it is lower than the application requirement
        if (!is_null(static::$memoryLimit)
            && $this->memoryInBytes($memoryLimit) < $this->memoryInBytes(static::$memoryLimit)) {
            $memoryLimit = static::$memoryLimit;
        }

        // Set if not -1
        if (static::$memoryLimit != -1) {
            @ini_set('memory_limit', $memoryLimit);
        }

        static::$memoryLimitSet = true;
    }
}
