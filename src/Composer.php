<?php

namespace Larapack\Hooks;

use Illuminate\Config\Repository;
use Illuminate\Filesystem\Filesystem;

class Composer
{
    protected $location;
    protected $filesystem;
    protected $items;

    public function __construct($location)
    {
        $this->filesystem = app(Filesystem::class);
        $this->location = $location;

        $this->read();
    }

    public function setRepository($name, $info)
    {
        if (!$this->items->has('repositories')) {
            $this->items['repositories'] = [];
        }

        $this->items->set('repositories.'.$name, $info);

        return $this;
    }

    public function setConfig($key, $value)
    {
        if (!$this->items->has('config')) {
            $this->items['config'] = [];
        }

        $this->items->set('config.'.$key, $value);

        return $this;
    }

    public function save()
    {
        $this->filesystem->put($this->location, $this->encode($this->items->all()));
    }

    public function unset($key)
    {
        $this->items[$key];

        return $this;
    }

    protected function read()
    {
        $this->items = new Repository($this->decode(
            $this->filesystem->get($this->location)
        ));
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
