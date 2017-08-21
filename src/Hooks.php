<?php

namespace Larapack\Hooks;

use Carbon\Carbon;
use Composer\XdebugHandler;
use Illuminate\Filesystem\Filesystem;
use Larapack\Hooks\Support\MemoryManager;
use Larapack\Hooks\Support\RawOutput;
use Symfony\Component\Console\Input\ArrayInput;

class Hooks extends MemoryManager
{
    // protected static $remote = 'https://larapack.io';
    // protected static $remote = 'https://testing.larapack.io';
    protected static $remote = 'http://larapack.dev';

    protected $filesystem;
    protected $hooks = [];
    protected $lastRemoteCheck;
    protected $outdated = [];

    protected $composer;
    protected $composerOutput;

    protected static $useVersionWildcardOnUpdate = false;
    protected static $versionWildcard = '*';
    protected static $localVersion = 'dev-master';

    public function __construct(Filesystem $filesystem)
    {
        $this->filesystem = $filesystem;

        $this->ensureCache();

        $this->prepareComposer();
        $this->readOutdated();

        $this->readJsonFile();

        $this->composerJson = new Composer(base_path('composer.json'));

        // Create output for XdebugHandler and Application
        $output = new RawOutput();
        $xdebug = new XdebugHandler($output);
        $xdebug->check();

        $this->composerOutput[] = $output;

        $this->prepareMemoryLimit();
    }

    public static function setUseVersionWildcardOnUpdate($boolean)
    {
        static::$useVersionWildcardOnUpdate = $boolean;
    }

    public static function useVersionWildcardOnUpdate($boolean = true)
    {
        static::setUseVersionWildcardOnUpdate($boolean);
    }

    public static function enableVersionWildcardOnUpdate()
    {
        static::setUseVersionWildcardOnUpdate(true);
    }

    public static function disableVersionWildcardOnUpdate()
    {
        static::setUseVersionWildcardOnUpdate(false);
    }

    public static function getUseVersionWildcardOnUpdate()
    {
        return static::$useVersionWildcardOnUpdate;
    }

    public function readOutdated()
    {
        $file = base_path('hooks/outdated.json');

        if ($this->filesystem->exists($file)) {
            $this->outdated = json_decode($this->filesystem->get($file), true);
        }
    }

    public function prepareComposer()
    {
        // Set environment
        //putenv('COMPOSER_BINARY='.realpath($_SERVER['argv'][0]));

        // Prepare Composer Application instance
        $this->composer = new \Composer\Console\Application();
        $this->composer->setAutoExit(false);
        $this->composer->setCatchExceptions(false);
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

    //--------------------------------------------------------------------- SETUP PROCESS

    /**
     * Install hook.
     *
     * @param $name
     *
     * @throws \Larapack\Hooks\Exceptions\HookAlreadyInstalledException
     */
    public function install($name, $version = null)
    {
        // Check if already installed
        if ($this->installed($name)) {
            throw new Exceptions\HookAlreadyInstalledException("Hook [{$name}] is already installed.");
        }

        event(new \Larapack\Hooks\Events\InstallingHook($name));
        // event(new Events\InstallingHook($name));

        /*
         * Prepare a repository if the hook is located locally
        if ($this->local($name)) {
            $this->prepareLocalInstall($name);

            if (is_null($version)) {
                $version = static::$localVersion;
            }
        }

        // Require hook
        $_hookname = $name.(is_null($version) ? '' : ':'.$version);
        $res = $this->composerRequire([$_hookname]); // TODO: Save Composer output somewhere
dd($res, $_hookname);
         */

        // TODO: Handle the case when Composer outputs:
        // Your requirements could not be resolved to an installable set of packages.
        //
        //   Problem 1
        //   - The requested package composer-github-hook v0.0.1 exists as composer-github-hook[dev-master]
        //     but these are rejected by your constraint.
        //
        //  Problem 2
        //  - The requested package voyager-templates could not be found in any version, there may
        //    be a typo in the package name.

        // TODO: Move to Composer Plugin
        // $this->readJsonFile();

        // Update hooks.json
        $this->hooks[$name]->update(['installed' => true]);
        $this->remakeJson();

        event(new Events\InstalledHook($this->hooks[$name]));
    }

    /**
     * Prepare Hook Local Installation.
     *
     * @param string $name
     *
     * @return void
     */
    public function prepareLocalInstall($name)
    {
        $this->composerJson->addRepository($name, [
            'type' => 'vcs',
            'url'  => "hooks/{$name}",
        ])->save();
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

        if (!$this->local($name)) {
            // TODO: Save Composer output somewhere
            $this->composerRemove([$name]);
        }

        if ($this->enabled($name)) {
            event(new Events\DisablingHook($hook));

            event(new Events\DisabledHook($hook));
        }

        // TODO: Run scripts for uninstall
        $hook->update([
            'enabled'   => false,
            'installed' => false,
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
        if (!$this->downloaded($name)) {
            throw new Exceptions\HookNotFoundException("Hook [{$name}] not found.");
        }

        // Check if installed
        if (!$this->installed($name)) {
            throw new Exceptions\HookNotInstalledException("Hook [{$name}] not installed.");
        }

        event(new Events\UpdatingHook($this->hooks[$name]));

        if (is_null($version)) {
            if (static::$useVersionWildcardOnUpdate) {
                $version = static::$versionWildcard;
            }

            // Prepare a repository if the hook is located locally
            if ($this->local($name)) {
                $version = static::$localVersion;
            }
        }

        // Require hook
        if (is_null($version)) {
            $this->composerRequire([$name]); // TODO: Save Composer output somewhere
        } else {
            $this->composerRequire([$name.':'.$version]); // TODO: Save Composer output somewhere
        }

        // TODO: Handle the case when Composer outputs:
        // Your requirements could not be resolved to an installable set of packages.
        //
        //      Problem 1
        //        - The requested package composer-github-hook v0.0.1 exists as composer-github-hook[dev-master]
        //          but these are rejected by your constraint.

        // TODO: Move to Composer Plugin
        $this->readJsonFile();
        $this->remakeJson();

        // TODO: Run scripts for update

        event(new Events\UpdatedHook($this->hooks[$name]));

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
        if (!$this->downloaded($name)) {
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

        // TODO: Run scripts for enable

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
        if (!$this->downloaded($name)) {
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

        // TODO: Run scripts for disable

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
        if ($this->downloaded($name)) {
            throw new Exceptions\HookAlreadyExistsException("Hook [{$name}] already exists.");
        }

        event(new Events\MakingHook($name));

        // Create folder for the new hook
        $this->filesystem->makeDirectory(base_path("hooks/{$name}"));

        // make stub files
        /*
        $this->filesystem->put(
            base_path("hooks/{$name}/hook.json"),
            json_encode($data)
        );
        */

        // Make composer.json
        $composer = [
            'name' => $name,
        ];
        $this->filesystem->put(
            base_path("hooks/{$name}/composer.json"),
            json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        event(new Events\MadeHook($name));
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
     * Check if hook is downloaded.
     *
     * @param $name
     *
     * @return bool
     */
    public function downloaded($name)
    {
        return $this->filesystem->isDirectory(base_path("hooks/{$name}"))
            || $this->filesystem->isDirectory(base_path("vendor/{$name}"));
    }

    /**
     * Get the latest version number of a hook if outdated.
     *
     * @param $name
     *
     * @return string|null
     */
    public function outdated($name)
    {
        if (isset($this->outdated[$name])) {
            return $this->outdated[$name];
        }
    }

    /**
     * Get enabled hooks.
     *
     * @return array
     */
    public function getEnabled()
    {
        if (!count($this->hooks()) > 0) {
            return [];
        }

        return $this->hooks()->where('enabled', true);
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
        if (!$this->downloaded($name)) {
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

        foreach ($data['hooks'] as $key => $hook) {
            $hooks[$key] = new Hook($hook);
        }

        if (isset($data['last_remote_check'])) {
            $this->lastRemoteCheck = Carbon::createFromTimestamp($data['last_remote_check']);
        }

        foreach ($this->readComposerHooks() as $name => $composerHook) {
            $hooks[$name] = $composerHook;
        }

        foreach ($this->readLocalHooks() as $name => $composerHook) {
            $hooks[$name] = $composerHook;
        }

        $this->hooks = collect($hooks);
    }

    /**
     * Read Composer Hooks.
     *
     * @return hooks
     */
    public function readComposerHooks()
    {
        $hooks = [];

        if (!$this->filesystem->exists(base_path('composer.lock'))) {
            return $hooks;
        }

        $composer = json_decode($this->filesystem->get(base_path('composer.lock')), true);
        foreach (array_get($composer, 'packages', []) as $package) {
            if (array_get($package, 'notification-url') == static::$remote.'/downloads') {
                $hooks[$package['name']] = new Hook($package);
            }
        }

        return $hooks;
    }

    /**
     * Read Local Hooks.
     *
     * @return hooks
     */
    public function readLocalHooks()
    {
        $hooks = [];
        $directories = array_except($this->filesystem->directories(base_path('hooks')), ['.', '..']);
        foreach ($directories as $directory) {
            $composer = json_decode($this->filesystem->get($directory.'/composer.json'), true);

            if (!is_null($composer) && isset($composer['name'])) {
                $hooks[$composer['name']] = new Hook($composer);
            }
        }

        return $hooks;
    }

    /**
     * Read Remote Hooks.
     *
     * @return hooks
     */
    public function readRemoteHooks()
    {
        $hooks = [];
        $remotes = json_decode(file_get_contents($this->getRemote().'/api/hooks'), true);
        foreach ($remotes as $hook) {
            $hooks[$hook['name']] = new Hook($hook);
        }

        return $hooks;
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

    public function composerRequire(array $packages)
    {
        return $this->runComposer([
            'command'  => 'require',
            'packages' => $packages,
        ]);
    }

    public function composerRemove(array $package)
    {
        return $this->runComposer([
            'command' => 'remove',
            'package' => $package,
        ]);
    }

    /**
     * Refresh hooks cache.
     *
     * @return void
     */
    public function refreshCache()
    {
        $hooks = [];

        $data = json_decode($this->filesystem->get(base_path('hooks/hooks.json')), true);

        /*
         * Read Remote Hooks
         */
        $remoteHooks = json_decode(file_get_contents($this->getRemote().'/api/hooks'), true);

        $localHooks = $this->getLocalHooks();

        // Get all enabled hooks and build cache from there.
        foreach ($data['hooks'] as $key => $hook) {
            if ($hook['enabled']) {
                $hooks[$key] = new Hook($hook);
                // if ($hook['type'] != 'local') {
                //     $hooks[$key]->remote = $this->getRemoteDetails($key);
                // }
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

        // Merge local hooks
        foreach ($localHooks as $hook) {
            $hooks[$hook] = new Hook($this->makeDefaultHookData($hook, 'local'));
        }

        // Merge remote hooks
        foreach ($remoteHooks as $hook) {
            $_hook = $this->makeHookData($hook['type'], [
                'name'   => $hook['name'],
                'remote' => $this->getRemoteDetails($hook['name']),
            ]);

            $hooks[$hook['name']] = new Hook($_hook);
        }

        $this->hooks = collect($hooks);

        $this->remakeJson();
    }

    public function runComposer($input)
    {
        $input = new ArrayInput(array_merge([
            '--working-dir' => base_path('/'),
        ], $input));

        $this->composer->run($input, $output = new RawOutput());

        $this->composerOutput[] = $output;

        return $output->output();
    }

    public function checkForUpdates()
    {
        $output = $this->runComposer([
            'command'  => 'outdated',
            '--format' => 'json',
        ]);

        $outdated = [];
        $hooks = [];
        $results = json_decode($output, true);

        foreach (array_get($results, 'installed', []) as $package) {
            if (isset($this->hooks[array_get($package, 'name')])) {
                $outdated[$package['name']] = $package['latest'];
                $hook = $this->hooks[$package['name']];
                $hook->setLatest($package['latest']);
                $hooks[] = $hook;
            }
        }

        $this->filesystem->put(
            base_path('hooks/outdated.json'),
            json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        $this->lastRemoteCheck = Carbon::now();

        $this->remakeJson();

        return collect($hooks);
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
     * Ensure cache requirements.
     * This will save us validations methods all over the code.
     *
     * @return void
     */
    private function ensureCache()
    {
        // Folder for hooks exist
        if (!$this->filesystem->exists(base_path('hooks'))) {
            $this->filesystem->makeDirectory(base_path('hooks'));
        }

        // Cache file hook.json exist
        if (!$this->filesystem->exists(base_path('hooks/hooks.json'))) {
            $this->remakeJson();

            // Ensure the hook.json has a valid structure
        } else {
            $data = json_decode($this->filesystem->get(base_path('hooks/hooks.json')), true);
            if (!isset($data['hooks'])) {
                $this->remakeJson();
            }
        }
    }
}
