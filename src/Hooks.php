<?php

namespace Larapack\Hooks;

use Carbon\Carbon;
use Composer\XdebugHandler;
use Larapack\Hooks\Composer;
use Illuminate\Support\Collection;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Process\Process;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\StreamOutput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Process\Exception\ProcessFailedException;

class Hooks
{
    protected static $remote = 'https://larapack.io';

    protected $filesystem;
    protected $hooks;
    protected $lastRemoteCheck;

    protected $composer;
    protected $composerOutput;

    protected static $scriptVariables = [];

    public function __construct(Filesystem $filesystem)
    {
        $this->filesystem = $filesystem;
        $this->prepareComposer();
        $this->readJsonFile();

        $this->composerJson = new Composer(base_path('composer.json'));
    }

    public function prepareComposer()
    {
        if (PHP_SAPI !== 'cli') {
            echo 'Warning: Composer should be invoked via the CLI version of PHP, not the '.PHP_SAPI.' SAPI'.PHP_EOL;
        }

        error_reporting(-1);

        // Create output for XdebugHandler and Application
        $this->composerOutput = new RawOutput;
        $xdebug = new XdebugHandler($this->composerOutput);
        $xdebug->check();
        unset($xdebug);

        if (function_exists('ini_set')) {
            @ini_set('display_errors', 1);
            $memoryInBytes = function ($value) {
                $unit = strtolower(substr($value, -1, 1));
                $value = (int) $value;
                switch($unit) {
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
            };
            $memoryLimit = trim(ini_get('memory_limit'));
            // Increase memory_limit if it is lower than 1.5GB
            if ($memoryLimit != -1 && $memoryInBytes($memoryLimit) < 1024 * 1024 * 1536) {
                @ini_set('memory_limit', '1536M');
            }
            unset($memoryInBytes, $memoryLimit);
        }

        // Set environment
        putenv('COMPOSER_BINARY='.realpath($_SERVER['argv'][0]));
        //putenv('COMPOSER='.realpath(base_path('composer.json')));
        
        // Prepare Composer Application instance
        $this->composer = new \Composer\Console\Application();
        $this->composer->setAutoExit(false);
        $this->composer->setCatchExceptions(false);
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
        // Check if already installed
        if ($this->installed($name)) {
            throw new Exceptions\HookAlreadyInstalledException("Hook [{$name}] is already installed.");
        }

        event(new Events\InstallingHook($name));

        // Prepare a repository if the hook is located locally
        if ($this->local($name)) {
            $this->prepareLocalInstallation($name);

            if (is_null($version)) {
                $version = 'dev-master';
            }
        }

        // Require hook
        if (is_null($version)) {
            $this->composerRequire([$name]);
        } else {
            $this->composerRequire([$name.':'.$version]);
        }

        $this->readJsonFile();

        /*
        // download hook if not local
        if (!$this->downloaded($name)) {
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

        // Add data to json
        $hook = new Hook($data);
        $hook->update(['version' => $version]);
        $this->hooks[$name] = $hook;
        $this->remakeJson();

        $this->dumpAutoload();
        */

        // TODO: Move to Composer Plugin
        // Run install scripts
        //$this->runScripts($name, 'install');

        // TODO: Move to Composer Plugin
        $this->readJsonFile();
        event(new Events\InstalledHook($this->hooks[$name]));
    }

    public function prepareLocalInstallation($name)
    {
        $this->composerJson->setRepository($name, [
            'type' => 'vcs',
            'url' => "hooks/{$name}",
        ]);

        $this->composerJson->save();
    }

    /**
     * Uninstall a hook.
     *
     * @param $name
     * @param $keep boolean
     *
     * @throws \Larapack\Hooks\Exceptions\HookNotInstalledException
     */
    public function uninstall($name, $keep = false)
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

        $hooks = $this->hooks()->where('name', '!=', $name);
        $this->hooks = $hooks;

        $this->remakeJson();

        event(new Events\UninstalledHook($name));

        if (!$keep) {
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
        if ($this->downloaded($name)) {
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
        /*
        $data = $this->makeHookData('local', [
            'name' => $name,
        ]);
        */

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
        return isset($this->hooks[$name]);
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
     * @param bool $enable
     *
     * @return array
     */
    public function makeDefaultHookData($name, $type, $enable = false)
    {
        return [
            'name'    => $name,
            'type'    => $type,
            'version' => null,
            'enabled' => $enable,
        ];
    }

    /**
     * Read hooks.json file.
     */
    public function readJsonFile()
    {
        $hooks = [];

        if (!$this->filesystem->exists(base_path('hooks'))) {
            $this->filesystem->makeDirectory(base_path('hooks'));
        }

        if (!$this->filesystem->exists(base_path('hooks/hooks.json'))) {
            $this->filesystem->put(base_path('hooks/hooks.json'), '{}');
        }

        $data = json_decode($this->filesystem->get(base_path('hooks/hooks.json')), true);

        if (isset($data['hooks'])) {
            foreach ($data['hooks'] as $key => $hook) {
                $hooks[$key] = new Hook($hook);
            }
        }

        if (isset($data['last_remote_check'])) {
            $this->lastRemoteCheck = Carbon::createFromTimestamp($data['last_remote_check']);
        }

        foreach ($this->readComposerHooks() as $name => $composerHook) {
            if (!isset($hooks[$name])) {
                $hooks[$name] = $composerHook;
            }
        }

        $this->hooks = collect($hooks);
    }

    public function readComposerHooks($file = null)
    {
        if (is_null($file)) {
            $file = base_path('composer.lock');
        }

        $hooks = [];
        $composer = [];
        if ($this->filesystem->exists($file)) {
            $composer = json_decode($this->filesystem->get($file), true);
        }

        foreach (array_get($composer, 'packages', []) as $package) {
            if (array_get($package, 'notification-url') == static::$remote.'/downloads') {
                $hooks[$package['name']] = new Hook($package);
            }
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
            'command' => 'require',
            'packages' => $packages,
        ]);
    }

    public function runComposer($input)
    {
        $input = new ArrayInput(array_merge([
            '--working-dir' => base_path('/'),
        ], $input));
        $this->composer->run($input, $this->composerOutput);

        return $this->composerOutput->output();
    }

    /**
     * Get the composer command for the environment.
     *
     * @return string
     */
    /*
    protected function findComposer()
    {
        if (file_exists(getcwd().'/composer.phar')) {
            return '"'.PHP_BINARY.'" '.getcwd().'/composer.phar';
        }

        return 'composer';
    }
    */

    /**
     * Dumps composer autoload.
     */
    /*
    public function dumpAutoload()
    {
        $composer = $this->findComposer();

        $process = new Process($composer.' dump-autoload');
        $process->setWorkingDirectory(base_path())->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
    }
    */
}

class RawOutput extends \Symfony\Component\Console\Output\Output
{
    protected $content;

    public function doWrite($message, $newline)
    {
        $this->content .= $message;

        if ($newline) {
            $this->content .= "\n";
        }
    }

    public function output()
    {
        return $this->content;
    }
}
