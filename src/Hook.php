<?php

namespace Larapack\Hooks;

use ArrayAccess;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Filesystem\Filesystem;

class Hook implements ArrayAccess, Arrayable
{
    protected $name;
    protected $description = 'This is a hook.';
    protected $version;
    protected $type;
    protected $enabled = false;
    protected $remote = [];
    protected $scripts = [];
    protected $composer = null;

    protected $filesystem;

    protected static $jsonParameters = ['description', 'scripts', 'provider', 'providers'];

    public function __construct($data)
    {
        $this->filesystem = app(Filesystem::class);

        $this->update($data);

        $this->loadJson();
        $this->loadComposer();
    }

    public function loadComposer()
    {
        $file = base_path('hooks/'.$this->name.'/composer.json');

        if ($this->filesystem->exists($file)) {
            $this->composer = json_decode($this->filesystem->get($file), true);

            if (!isset($this->composer['version'])) {
                $this->composer['version'] = $this->version;
                $this->filesystem->put($file, json_encode($this->composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            }
        }
    }

    public function loadJson($path = null)
    {
        if (is_null($path)) {
            $path = base_path("hooks/{$this->name}/hook.json");
        }

        $this->mergeWithJson($path);
    }

    public function update(array $parameters)
    {
        foreach ($parameters as $key => $value) {
            $this->setAttribute($key, $value);
        }
    }

    public function scripts($event)
    {
        if (isset($this->scripts[$event])) {
            return $this->scripts[$event];
        }

        return [];
    }

    public function hasUpdateAvailable()
    {
        $remoteVersion = $this->getRemoteVersionAttribute();

        if (is_null($remoteVersion)) {
            return false;
        }

        return $remoteVersion != $this->version;
    }

    public function mergeWithJson($path)
    {
        if ($this->filesystem->exists($path)) {
            $data = json_decode($this->filesystem->get($path), true);

            $this->update(
                collect($data)->only(static::$jsonParameters)->all()
            );
        }
    }

    public function setAttribute($key, $value)
    {
        $method = camel_case('set_'.$key.'_attribute');

        if (method_exists($this, $method)) {
            return $this->$method($value);
        }

        $this->$key = $value;
    }

    public function getAttribute($key)
    {
        $method = camel_case('get_'.$key.'_attribute');

        if (method_exists($this, $method)) {
            return $this->$method();
        }

        return $this->$key;
    }

    public function getRemoteVersionAttribute()
    {
        if (isset($this->remote['version'])) {
            return $this->remote['version'];
        }
    }

    public function getComposerNameAttribute()
    {
        return array_get($this->composer, 'name');
    }

    public function offsetExists($offset)
    {
        return isset($this->$offset);
    }

    public function offsetGet($offset)
    {
        return $this->getAttribute($offset);
    }

    public function offsetSet($offset, $value)
    {
        $this->setAttribute($offset, $value);
    }

    public function offsetUnset($offset)
    {
        unset($this->$offset);
    }

    public function __get($key)
    {
        return $this->getAttribute($key);
    }

    public function __set($key, $value)
    {
        return $this->setAttribute($key, $value);
    }

    public function toArray()
    {
        return [
            'name'        => $this->name,
            'description' => $this->description,
            'version'     => $this->version,
            'type'        => $this->type,
            'enabled'     => (bool) $this->enabled,
            'scripts'     => (array) $this->scripts,
            'remote'      => (array) $this->remote,
        ];
    }

    public function __toArray()
    {
        return $this->toArray();
    }
}
