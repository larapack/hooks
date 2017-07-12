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

    protected $enabled = false;
    protected $installed = false;
    protected $remote = [];
    /*
                                        >>>>>>> origin/fetch-hooks-from-api-backup
    protected $scripts = [];
     */

    protected $latest = null;

    protected $composerJson;

    protected static $jsonParameters = ['description', 'enabled'];

    public function __construct($data)
    {
        $this->update($data);

        $this->loadJson();
    }

    public function getProviders()
    {
        return $this->getComposerHookKey('providers', []);
    }

    public function getComposerHookKey($key, $default = null)
    {
        if (is_null($this->composerJson)) {
            $this->loadComposerJson();
        }

        if (!isset($this->composerJson['extra'])) {
            return $default;
        }

        if (!isset($this->composerJson['extra']['hook'])) {
            return $default;
        }

        if (!isset($this->composerJson['extra']['hook'][$key])) {
            return $default;
        }

        return $this->composerJson['extra']['hook'][$key];
    }

    public function getAliases()
    {
        return $this->getComposerHookKey('aliases', []);
    }

    public function loadComposerJson()
    {
        $this->composerJson = json_decode($this->getComposerJsonFile(), true);
    }

    public function getComposerJsonFile()
    {
        $path = 'vendor/'.$this->name;

        if ($this->isLocal()) {
            $path = 'hooks/'.$this->name;
        }

        return app(Filesystem::class)->get(base_path($path.'/composer.json'));
    }

    public function setLatest($latest)
    {
        $this->latest = $latest;
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

    public function outdated()
    {
        if (is_null($this->latest)) {
            $this->latest = app('hooks')->outdated($hook);
        }

        return $this->latest != $this->version;
    }

    public function mergeWithJson($path)
    {
        $filesystem = app(Filesystem::class);

        if ($filesystem->exists($path)) {
            $data = json_decode($filesystem->get($path), true);

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
            'enabled'     => (bool) $this->enabled,
            'installed'   => (bool) $this->installed,
            'remote'      => (array) $this->remote,
        /*
                                            >>>>>>> origin/fetch-hooks-from-api-backup
            'scripts'     => (array) $this->scripts,
         */
        ];
    }

    public function __toArray()
    {
        return $this->toArray();
    }

    public function isLocal()
    {
        return app(Filesystem::class)->isDirectory(base_path("hooks/{$this->name}"));
    }
}
