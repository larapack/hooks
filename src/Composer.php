<?php

namespace Larapack\Hooks;

use Illuminate\Config\Repository;
use Illuminate\Filesystem\Filesystem;

class Composer
{
    protected $location;
    protected $filesystem;
    protected $items;
    protected $changed = false;

    public function __construct($location = null)
    {
        if (is_null($location)) {
            $location = base_path('composer.json');
        }

        $this->filesystem = app(Filesystem::class);
        $this->location = $location;

        $this->read();
    }

    public function addRepository($name, $info)
    {
        if (!$this->items->has('repositories')) {
            $this->items['repositories'] = [];
        }

        $this->items->set('repositories.'.$name, $info);

        $this->changed = true;

        return $this;
    }

    public function addConfig($key, $value)
    {
        if (!$this->items->has('config')) {
            $this->items['config'] = [];
        }

        $this->items->set('config.'.$key, $value);

        $this->changed = true;

        return $this;
    }

    public function set($key, $value)
    {
        $this->items->set($key, $value);

        $this->changed = true;

        return $this;
    }

    public function get($key, $default = null)
    {
        return $this->items->get($key, $default);
    }

    public function has($key)
    {
        return $this->items->has($key);
    }

    public function save()
    {
        if ($this->changed) {
            $this->filesystem->put($this->location, $this->encode($this->items->all()));

            $this->changed = false;
        }
    }

    protected function read()
    {
        $this->items = new Repository($this->decode(
            $this->filesystem->get($this->location)
        ));

        $this->changed = false;
    }

    protected function decode($string)
    {
        return json_decode($string, true);
    }

    protected function encode(array $array)
    {
        return json_encode($array, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
