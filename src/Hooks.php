<?php

namespace Larapack\Hooks;

use Carbon\Carbon;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class Hooks
{
    protected static $remote = 'https://larapack.io';

    protected $filesystem;
    protected $hooks;
    protected $lastRemoteCheck;

    protected static $scriptVariables = [];

    public function __construct(Filesystem $filesystem)
    {
        $this->filesystem = $filesystem;

        $this->provision();

        $this->readJsonFile();
    }

    /**
     * Create script value.
     *
     * @param string $key
     * @param mixed  $value
     */
    public static function addScriptVariable($key, $value)
    {
        static::$scriptVariables[$key] = $value;
    }

    /**
     * Get remote url.
     *
     * @return string
     */
    public static function getRemote()
    {
        return static::$remote;
    }

    /**
     * Set remote url.
     *
     * @param $remote
     */
    public static function setRemote($remote)
    {
        static::$remote = $remote;
    }

    public function getLastRemoteCheck()
    {
        return $this->lastRemoteCheck;
    }

    public function setLastRemoteCheck(Carbon $carbon)
    {
        $this->lastRemoteCheck = $carbon;
    }

    /**
     * Install hook.
     *
     * @param $name
     *
     * @throws \Larapack\Hooks\Exceptions\HookAlreadyInstalledException
     */
    public function install($name, $version = null)
    {
        $this->classmap('hooks');

        // check database if already installed
        if ($this->installed($name)) {
            throw new Exceptions\HookAlreadyInstalledException("Hook [{$name}] is already installed.");
        }

        event(new Events\InstallingHook($name));

        // download hook if not local
        if (!$this->local($name)) {
            // Get remote details
            $remote = $this->getRemoteDetails($name);

            // Download hook.
            $this->download($remote, $version);
        }


        // Add this hook to JSON
        if (isset($remote)) {
            $data = $this->makeHookData($remote['type'], $remote);

            if (is_null($version)) {
                $version = $remote['version'];
            }
        } else {
            $data = $this->makeHookData('local', [
                'name' => $name,
            ]);
        }

        if (!is_null($version)) {
            $data = array_merge(['version' => $version], $data);
        }

        $data = array_merge(['installed' => true], $data);

        // Add data to json
        $hook = new Hook($data);

        $this->hooks[$name] = $hook;
        $this->remakeJson();

        $this->dumpAutoload();

        // Run install scripts
        $this->runScripts($name, 'install');

        event(new Events\InstalledHook($hook));
    }

    /**
     * Uninstall a hook.
     *
     * @param $name
     * @param $delete boolean
     *
     * @throws \Larapack\Hooks\Exceptions\HookNotInstalledException
     */
    public function uninstall($name, $delete = false)
    {
        // Check if installed
        if (!$this->installed($name)) {
            throw new Exceptions\HookNotInstalledException("Hook [{$name}] is not installed.");
        }

        $hook = $this->hook($name);

        $hook->loadJson();

        event(new Events\UninstallingHook($hook));

        if ($this->enabled($name)) {
            event(new Events\DisablingHook($hook));

            $this->runScripts($name, 'disable');

            event(new Events\DisabledHook($hook));
        }

        $this->runScripts($name, 'uninstall');

        $hook->update([
            'enabled'   => false,
            'installed' => false
        ]);

        $this->remakeJson();

        event(new Events\UninstalledHook($name));

        if ($delete) {
            $this->filesystem->deleteDirectory(base_path("hooks/{$name}"));
        }
    }

    /**
     * Update hook.
     *
     * @param $name
     * @param string|null $version
     *
     * @throws \Larapack\Hooks\Exceptions\HookNotFoundException
     * @throws \Larapack\Hooks\Exceptions\HookNotInstalledException
     *
     * @return bool
     */
    public function update($name, $version = null)
    {
        // Check if hook exists
        if (!$this->local($name)) {
            throw new Exceptions\HookNotFoundException("Hook [{$name}] not found.");
        }

        // Check if installed
        if (!$this->installed($name)) {
            throw new Exceptions\HookNotInstalledException("Hook [{$name}] not installed.");
        }

        $remote = $this->getRemoteDetails($name);

        if (is_null($version)) {
            $version = $remote['version'];
        }

        $hook = $this->hook($name);

        $hook->loadJson();

        if ($version == $hook->version) {
            return false;
        }

        event(new Events\UpdatingHook($hook));

        // Download hook.
        $this->download($remote, $version, true);

        // Update json
        $data = $this->makeHookData($remote['type'], $remote);

        if (!is_null($version)) {
            $data = array_merge(['version' => $version], $data);
        }

        $hook = new Hook($data);
        $hook->update(['version' => $version]);
        $this->hooks[$name] = $hook;

        $this->remakeJson();

        $this->runScripts($name, 'update');

        event(new Events\UpdatedHook($hook));

        return true;
    }

    /**
     * Enable hook.
     *
     * @param $name
     *
     * @throws \Larapack\Hooks\Exceptions\HookNotFoundException
     * @throws \Larapack\Hooks\Exceptions\HookNotInstalledException
     * @throws \Larapack\Hooks\Exceptions\HookAlreadyEnabledException
     */
    public function enable($name)
    {
        // Check if exists
        if (!$this->local($name)) {
            throw new Exceptions\HookNotFoundException("Hook [{$name}] not found.");
        }

        if (!$this->installed($name)) {
            throw new Exceptions\HookNotInstalledException("Hook [{$name}] not installed.");
        }

        if ($this->enabled($name)) {
            throw new Exceptions\HookAlreadyEnabledException("Hook [{$name}] already enabled.");
        }

        $hook = $this->hook($name);

        $hook->loadJson();

        event(new Events\EnablingHook($hook));

        $this->runScripts($name, 'enable');

        $this->hooks[$name]->update(['enabled' => true]);

        $this->remakeJson();

        event(new Events\EnabledHook($hook));
    }

    /**
     * Disable a hook.
     *
     * @param $name
     *
     * @throws \Larapack\Hooks\Exceptions\HookNotFoundException
     * @throws \Larapack\Hooks\Exceptions\HookNotEnabledException
     * @throws \Larapack\Hooks\Exceptions\HookNotInstalledException
     */
    public function disable($name)
    {
        // Check if exists
        if (!$this->local($name)) {
            throw new Exceptions\HookNotFoundException("Hook [{$name}] not found.");
        }

        if (!$this->installed($name)) {
            throw new Exceptions\HookNotInstalledException("Hook [{$name}] not installed.");
        }

        if (!$this->enabled($name)) {
            throw new Exceptions\HookNotEnabledException("Hook [{$name}] not enabled.");
        }

        $hook = $this->hook($name);

        $hook->loadJson();

        event(new Events\DisablingHook($hook));

        $this->runScripts($name, 'disable');

        $this->hooks[$name]->update(['enabled' => false]);

        $this->remakeJson();

        event(new Events\DisabledHook($hook));
    }

    /**
     * Make hook.
     *
     * @param $name
     *
     * @throws \Larapack\Hooks\Exceptions\HookAlreadyExistsException
     */
    public function make($name)
    {
        // Check if already exists
        if ($this->local($name)) {
            throw new Exceptions\HookAlreadyExistsException("Hook [{$name}] already exists.");
        }

        event(new Events\MakingHook($name));

        // Ensure hooks folder exists
        if (!$this->filesystem->isDirectory(base_path('hooks'))) {
            $this->filesystem->makeDirectory(base_path('hooks'));
        }

        // Create folder for the new hook
        $this->filesystem->makeDirectory(base_path("hooks/{$name}"));

        // Make hook data
        $data = $this->makeHookData('local', [
            'name' => $name,
        ]);

        // make stub files
        $this->filesystem->put(
            base_path("hooks/{$name}/hook.json"),
            json_encode($data)
        );

        event(new Events\MadeHook($name));
    }

    /**
     * Download hook.
     *
     * @param array $remote
     *
     * @throws \Larapack\Hooks\Exceptions\HookAlreadyExistsException
     */
    protected function download($remote, $version = null, $update = false)
    {
        $name = $remote['name'];

        if ($this->local($name) && !$update) {
            throw new Exceptions\HookAlreadyExistsException("Hook [{$name}] already exists.");
        }

        if (is_null($version) && isset($remote['version'])) {
            $version = $remote['version'];
        }

        // Download hook
        $downloader = app('hooks.downloaders.'.$remote['type']);
        $downloader->download($remote, $version);

        // Ensure hooks folder exists
        if (!$this->filesystem->isDirectory(base_path('hooks'))) {
            $this->filesystem->makeDirectory(base_path('hooks'));
        }

        // Remove old hook
        if ($this->filesystem->isDirectory(base_path("hooks/{$name}"))) {
            $this->filesystem->deleteDirectory(base_path("hooks/{$name}"));
        }

        // Place new hook on hooks folder
        $downloader->output(base_path("hooks/{$name}"));

        $this->updateDownloadCount($name);
    }

    /**
     * Update download count.
     */
    private function updateDownloadCount($name)
    {
        try {
            file_get_contents(static::$remote."/api/hooks/{$name}/downloaded?url=".url('/'));
        } catch (\Exception $exception) {
            // do nothing
        }
    }

    /**
     * Check hooks for updates.
     *
     * @param \Illuminate\Support\Collection $hooks
     *
     * @return \Illuminate\Support\Collection
     */
    public function checkForUpdates(Collection $hooks = null)
    {
        if (is_null($hooks)) {
            $hooks = $this->hooks();

            $this->lastRemoteCheck = Carbon::now();
        }

        foreach ($hooks->where('type', '!=', 'local')->all() as $hook) {
            // Get remote details
            $hook->remote = $this->getRemoteDetails($hook->name);
        }

        $this->remakeJson();

        return $hooks->filter(function (Hook $hook) {
            return $hook->hasUpdateAvailable();
        });
    }

    /**
     * Check if hook is already installed.
     *
     * @param $name
     *
     * @return bool
     */
    public function installed($name)
    {
        return isset($this->hooks[$name]) && $this->hooks[$name]->installed;
    }

    /**
     * Check if hook is enabled.
     *
     * @param $name
     *
     * @return bool
     */
    public function enabled($name)
    {
        return isset($this->hooks[$name]) && $this->hooks[$name]->enabled;
    }

    /**
     * Check if hook is disabled.
     *
     * @param $name
     *
     * @return bool
     */
    public function disabled($name)
    {
        return !$this->enabled($name);
    }

    /**
     * Check if hook is located locally.
     *
     * @param $name
     *
     * @return bool
     */
    public function local($name)
    {
        return $this->filesystem->isDirectory(base_path("hooks/{$name}"));
    }

    /**
     * Get hook information.
     *
     * @param $name
     *
     * @throws \Larapack\Hooks\Exceptions\HookNotFoundException
     * @throws \Larapack\Hooks\Exceptions\HookNotInstalledException
     *
     * @return \Larapack\Hooks\Hook
     */
    public function hook($name)
    {
        if (!$this->local($name)) {
            throw new Exceptions\HookNotFoundException("Hook [{$name}] not found.");
        }

        if (!$this->installed($name)) {
            throw new Exceptions\HookNotInstalledException("Hook [{$name}] not installed.");
        }

        return $this->hooks[$name];
    }

    /**
     * Get all hooks.
     *
     * @return \Illuminate\Support\Collection
     */
    public function hooks()
    {
        return $this->hooks;
    }

    /**
     * Get type of hook.
     *
     * @param $name
     *
     * @return string
     */
    public function type($name)
    {
        $hook = $this->hooks()->where('name', $name)->first();

        if (!is_null($hook)) {
            return $hook->type;
        }
    }

    /**
     * Get version of hook.
     *
     * @param $name
     *
     * @return string|null
     */
    public function version($name)
    {
        $data = $this->hook($name);

        return $data['version'];
    }

    /**
     * Get hook details from remote.
     *
     * @param $name
     *
     * @return array
     */
    public function getRemoteDetails($name)
    {
        // Get remote
        $remote = json_decode(file_get_contents($this->getRemote()."/api/hooks/{$name}.json"), true);

        if ($remote['exists'] !== true) {
            throw new \InvalidArgumentException("Hook [{$name}] does not exists.");
        }

        return $remote;
    }

    /**
     * Run scripts from hook.
     *
     * @param $name
     * @param array|string $events
     *
     * @return array
     */
    public function runScripts($name, $events)
    {
        $output = [];

        if (!is_array($events)) {
            $events = [$events];
        }

        $hook = $this->hook($name);
        $hook->loadJson();

        foreach ($events as $event) {
            foreach ($hook->scripts($event) as $script) {
                $script = $this->prepareScript($script);

                $process = new Process($script);
                $process->setWorkingDirectory(base_path())->run();

                if (!$process->isSuccessful()) {
                    throw new ProcessFailedException($process);
                }

                $output[] = $process->getOutput();
            }
        }

        return $output;
    }

    public function prepareScript($script)
    {
        foreach (static::$scriptVariables as $key => $value) {
            $script = str_replace('{'.$key.'}', $value, $script);
        }

        return $script;
    }

    /**
     * Make hook data for the type.
     *
     * @param $type
     * @param array $parameters
     *
     * @return mixed
     */
    public function makeHookData($type, array $parameters = [])
    {
        $method = 'make'.ucfirst(camel_case($type)).'HookData';

        if (!method_exists($this, $method)) {
            $method = 'makeDefaultHookData';
        }

        $parameters['type'] = $type;

        return app()->call([$this, $method], $parameters);
    }

    /**
     * Make default hook data.
     *
     * @param $name
     * @param $type
     * @param bool $enabled
     * @param bool $installed
     *
     * @return array
     */
    public function makeDefaultHookData($name, $type, $enabled = false, $installed = false)
    {
        return [
            'name'      => $name,
            'type'      => $type,
            'version'   => null,
            'enabled'   => $enabled,
            'installed' => $installed,
        ];
    }

    /**
     * Read hooks.json file.
     */
    public function readJsonFile()
    {
        $hooks = [];

        $data = json_decode($this->filesystem->get(base_path('hooks/hooks.json')), true);

        if (isset($data['hooks'])) {
            foreach ($data['hooks'] as $key => $hook) {
                $hooks[$key] = new Hook($hook);
            }
        }

        if (isset($data['last_remote_check'])) {
            $this->lastRemoteCheck = Carbon::createFromTimestamp($data['last_remote_check']);
        }

        $this->hooks = collect($hooks);
    }

    /**
     * Remake hooks.json file.
     */
    public function remakeJson()
    {
        $json = json_encode([
            'last_remote_check' => (!is_null($this->lastRemoteCheck) ? $this->lastRemoteCheck->timestamp : null),
            'hooks'             => $this->hooks(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        file_put_contents(base_path('hooks/hooks.json'), $json);
    }

    /**
     * Refresh cache file /hooks/hooks.json.
     *
     * @return void
     */
    public function refreshCache()
    {
        $hooks = [];

        $data = json_decode($this->filesystem->get(base_path('hooks/hooks.json')), true);

        $localHooks = $this->getLocalHooks();
        $remoteHooks = json_decode(file_get_contents($this->getRemote().'/api/hooks'), true);

        // If cache file exists, we need to get all enabled hooks
        // and build cache from there.
        if (isset($data['hooks'])) {
            foreach ($data['hooks'] as $key => $hook) {
                if ($hook['enabled']) {
                    $hooks[$key] = new Hook($hook);
                    if ($hook['type'] != 'local') {
                        $hooks[$key]->remote = $this->getRemoteDetails($key);
                    }
                }
            }

            // Exclude enabled hooks from localHooks
            $localHooks = array_filter($localHooks, function ($hook) use ($hooks) {
                return !in_array($hook, array_keys($hooks));
            });

            // Exclude enabled hooks from remoteHooks
            $remoteHooks = array_filter($remoteHooks, function ($hook) use ($hooks) {
                return !in_array($hook['name'], array_keys($hooks));
            });
        }

        // Merge local hooks
        foreach ($localHooks as $hook) {
            $hooks[$hook] = new Hook($this->makeDefaultHookData($hook, 'local'));
        }

        // Merge remote hooks
        foreach ($remoteHooks as $hook) {
            $_hook = $this->makeHookData($hook['type'], [
                'name'        => $hook['name'],
                'description' => $hook['description'],
                'remote'      => $this->getRemoteDetails($hook['name']),
            ]);

            $hooks[$hook['name']] = new Hook($_hook);
        }

        $this->hooks = collect($hooks);

        $this->lastRemoteCheck = Carbon::now();

        $this->remakeJson();
    }

    /**
     * Get the composer command for the environment.
     *
     * @return string
     */
    protected function findComposer()
    {
        if (file_exists(getcwd().'/composer.phar')) {
            return '"'.PHP_BINARY.'" '.getcwd().'/composer.phar';
        }

        return 'composer';
    }

    /**
     * Dumps composer autoload.
     */
    public function dumpAutoload()
    {
        $composer = $this->findComposer();

        $process = new Process($composer.' dump-autoload');
        $process->setWorkingDirectory(base_path())->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
    }

    /**
     * Add class map to composer autoload.
     *
     * @param string $path
     */
    public function classmap($path)
    {
        $json = $this->filesystem->get(base_path('composer.json'));
        $composer = json_decode($json, true);

        if (!isset($composer['autoload'])) {
            $composer['autoload'] = [];
        }

        if (!isset($composer['autoload']['classmap'])) {
            $composer['autoload']['classmap'] = [];
        }

        if (!in_array('hooks', $composer['autoload']['classmap'])) {
            $composer['autoload']['classmap'][] = $path;
        }

        $new = json_encode($composer, JSON_PRETTY_PRINT);

        if ($json != $new) {
            $this->filesystem->put(base_path('composer.json'), $new);
        }
    }

    /**
     * Get local hooks listed on folder `/hooks`.
     *
     * @return array
     */
    private function getLocalHooks()
    {
        $_hooks = $this->filesystem->directories(base_path('hooks'));

        return array_map(function ($_hook) {
            return substr($_hook, strrpos($_hook, '/') + 1);
        }, $_hooks);
    }

    /**
     * Provision hooks requirements.
     *
     * @return void
     */
    private function provision()
    {
        if (!$this->filesystem->exists(base_path('hooks'))) {
            $this->filesystem->makeDirectory(base_path('hooks'));
        }

        if (!$this->filesystem->exists(base_path('hooks/hooks.json'))) {
            $this->filesystem->put(base_path('hooks/hooks.json'), '{}');
        }

        if (!isset($this->hooks['hooks'])) {
            $this->refreshCache();
        }
    }
}
