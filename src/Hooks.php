<?php

namespace Larapack\Hooks;

use Carbon\Carbon;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Symfony\Component\Console\Input\ArrayInput;

class Hooks
{
    protected static $remote = 'https://larapack.io';

    protected $app;
    protected $filesystem;
    protected $migrator;
    protected $hooks;
    protected $lastRemoteCheck;
    protected $outdated = [];

    protected $composer;
    protected $composerOutput;

    protected $tempDirectories = [];

    protected static $memoryLimit = null;
    protected static $memoryLimitSet = false;

    protected static $useVersionWildcardOnUpdate = false;
    protected static $versionWildcard = '*';
    protected static $localVersion = '*';

    protected static $fakeDateTime = false;

    public function __construct(Filesystem $filesystem, Migrator $migrator)
    {
        $this->app = Application::getInstance();
        $this->filesystem = $filesystem;
        $this->migrator = $migrator;

        $this->prepareComposer();
        $this->readOutdated();
        $this->readJsonFile();

        $this->composerJson = new Composer(base_path('composer.json'));

        $this->composerOutput[] = new RawOutput();

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

    public static function fakeDateTime(Carbon $dateTime)
    {
        static::$fakeDateTime = $dateTime;
    }

    public function readOutdated()
    {
        $file = base_path('hooks/outdated.json');

        if ($this->filesystem->exists($file)) {
            $this->outdated = json_decode($this->filesystem->get($file), true);
        }
    }

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

        // Increaes memory_limit if it is lower than the application requirement
        if (!is_null(static::$memoryLimit) && $this->memoryInBytes($memoryLimit) < $this->memoryInBytes(static::$memoryLimit)) {
            $memoryLimit = static::$memoryLimit;
        }

        // Set if not -1
        if (static::$memoryLimit != -1) {
            @ini_set('memory_limit', $memoryLimit);
        }

        static::$memoryLimitSet = true;
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

    /**
     * Install hook.
     *
     * @param $name
     *
     * @throws \Larapack\Hooks\Exceptions\HookAlreadyInstalledException
     */
    public function install($name, $version = null, $migrate = true, $seed = true, $publish = true)
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
        $this->readJsonFile([$name]);
        $this->remakeJson();

        if ($migrate) {
            $this->migrateHook($this->hooks[$name]);
        }

        if ($seed) {
            $this->seedHook($this->hooks[$name]);
        }

        if ($publish) {
            $this->publishHook($this->hooks[$name]);
        }

        event(new Events\InstalledHook($this->hooks[$name]));
    }

    public function prepareLocalInstallation($name)
    {
        $this->composerJson->addRepository($name, [
            'type' => 'path',
            'url'  => "hooks/{$name}",
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
    public function uninstall($name, $delete = false, $unmigrate = true, $unseed = true, $unpublish = true)
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

            // Some logic could later be placed here

            event(new Events\DisabledHook($hook));
        }

        if ($unseed) {
            $this->unseedHook($hook);
        }

        if ($unmigrate) {
            $this->unmigrateHook($hook);
        }

        if ($unpublish) {
            $this->unpublishHook($hook);
        }

        $this->runComposer([
            'command'  => 'remove',
            'packages' => [$name],
        ]);

        $hooks = $this->hooks()->where('name', '!=', $name);
        $this->hooks = $hooks;

        $this->remakeJson();

        event(new Events\UninstalledHook($name));

        if ($delete && $hook->isLocal()) {
            $this->filesystem->deleteDirectory(base_path("hooks/{$name}"));
        }
    }

    /**
     * Update hook.
     *
     * @param $name
     * @param string|null $version
     * @param bool        $migrate
     * @param bool        $seed
     * @param bool        $publish
     * @param bool        $force
     *
     * @throws \Larapack\Hooks\Exceptions\HookNotFoundException
     * @throws \Larapack\Hooks\Exceptions\HookNotInstalledException
     *
     * @return bool
     */
    public function update($name, $version, $migrate = true, $seed = true, $publish = true, $force = false)
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

        if (!$force) {
            $this->makeTemponaryBackup($this->hooks[$name]);
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

        if ($migrate) {
            $this->migrateHook($this->hooks[$name]);
        }

        if ($seed) {
            $this->seedHook($this->hooks[$name]);
        }

        if ($publish) {
            $this->publishHook($this->hooks[$name], $force);
        }

        $this->clearTemponaryFiles();

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
        $studlyCase = studly_case($name);

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
        $this->filesystem->deleteDirectory(base_path("hooks/{$name}"));
        $this->filesystem->makeDirectory(base_path("hooks/{$name}"));

        // make stub files
        $this->makeStubFiles($name);

        event(new Events\MadeHook($name));
    }

    protected function makeStubFiles($name)
    {
        $replaces = [
            'kebab-case'          => $name,
            'snake_case'          => snake_case(str_replace('-', '_', $name)),
            'camelCase'           => camel_case(str_replace('-', '_', $name)),
            'StudlyCase'          => studly_case(str_replace('-', '_', $name)),
            'MIGRATION_DATE_TIME' => $this->migrationDateTimeString(),
        ];

        $files = $this->filesystem->allFiles(__DIR__.'/../stub');

        foreach ($files as $file) {
            if ($path = $file->getRelativePath()) {
                $parts = explode('/', str_replace('\\', '/', $path));

                $location = base_path("hooks/{$name}");

                foreach ($parts as $part) {
                    $location .= "/{$part}";

                    if (!$this->filesystem->isDirectory($location)) {
                        $this->filesystem->makeDirectory($location);
                    }
                }
            }

            $content = $this->replace($this->filesystem->get($file->getRealPath()), $replaces);
            $filename = $this->replace($file->getRelativePathname(), $replaces);

            $this->filesystem->put(base_path("hooks/{$name}/{$filename}"), $content);
        }
    }

    protected function migrationDateTimeString()
    {
        $dateTime = Carbon::now();

        if (static::$fakeDateTime) {
            $dateTime = static::$fakeDateTime;
        }

        return $dateTime->format('Y_m_d_His');
    }

    protected function replace($content, array $replaces)
    {
        return str_replace(array_keys($replaces), array_values($replaces), $content);
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
        if ($this->local($name)) {
            return $this->filesystem->isDirectory(base_path("hooks/{$name}"))
                && $this->filesystem->exists(base_path("hooks/{$name}/composer.json"));
        }

        return $this->filesystem->isDirectory(base_path("vendor/{$name}"));
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
    public function readJsonFile($localsIncluded = [])
    {
        $hooks = [];

        if (!$this->filesystem->exists(base_path('hooks'))) {
            $this->filesystem->makeDirectory(base_path('hooks'));
        }

        if (!$this->filesystem->exists(base_path('hooks/hooks.json'))) {
            $this->filesystem->put(base_path('hooks/hooks.json'), '{}');
        }

        $data = json_decode($this->filesystem->get(base_path('hooks/hooks.json')), true);
        $enabled = [];

        if (isset($data['hooks'])) {
            foreach ($data['hooks'] as $key => $hook) {
                if (!$this->filesystem->exists(base_path("hooks/{$key}/composer.json")) &&
                    !$this->filesystem->exists(base_path("vendor/{$key}/composer.json"))) {
                    continue; // This hook does not seem to exist anymore
                }

                $hooks[$key] = new Hook($hook);
                if ($hooks[$key]->enabled) {
                    $enabled[] = $key;
                }
            }
        }

        if (isset($data['last_remote_check'])) {
            $this->lastRemoteCheck = Carbon::createFromTimestamp($data['last_remote_check']);
        }

        foreach ($this->readComposerHooks() as $name => $composerHook) {
            $hooks[$name] = $composerHook;

            if (in_array($name, $enabled)) {
                $hooks[$name]->enabled = true;
            }
        }

        foreach ($this->readLocalHooks() as $name => $composerHook) {
            if (!isset($hooks[$name]) && !in_array($name, $localsIncluded)) {
                continue; // Do not show not-installed local hooks.
            }

            $hooks[$name] = $composerHook;

            if (in_array($name, $enabled)) {
                $hooks[$name]->enabled = true;
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

    public function readLocalHooks()
    {
        $hooks = [];
        $directories = array_except($this->filesystem->directories(base_path('hooks')), ['.', '..']);
        foreach ($directories as $directory) {
            if (!$this->filesystem->exists($directory.'/composer.json')) {
                continue;
            }

            $composer = json_decode($this->filesystem->get($directory.'/composer.json'), true);

            if (!is_null($composer) && isset($composer['name'])) {
                $composer['type'] = 'local';
                $hooks[$composer['name']] = new Hook($composer);
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
            'command'  => 'require',
            'packages' => $packages,
        ]);
    }

    public function runComposer($input)
    {
        $input = new ArrayInput(array_merge([
            '--working-dir' => base_path('/'),
        ], $input));

        $this->composer->run($input, $output = new RawOutput());

        $this->composerOutput[] = $output;

        Application::setInstance($this->app);

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
     * Run migrations found for a specific hook.
     *
     * @param \Larapack\Hooks\Hook $hook
     */
    protected function migrateHook(Hook $hook)
    {
        $migrations = (array) $hook->getComposerHookKey('migrations', []);

        foreach ($migrations as $path) {
            if ($this->filesystem->isDirectory($hook->getPath().'/'.$path)) {
                $this->migrator->run($this->realPath([$path], $hook->getPath().'/')->all());
            } else {
                $this->migrator->runFiles($this->realPath([$path], $hook->getPath().'/')->all());
            }
        }
    }

    /**
     * Rollback migrations found for a specific hook.
     *
     * @param \Larapack\Hooks\Hook $hook
     */
    protected function unmigrateHook(Hook $hook)
    {
        $migrations = (array) $hook->getComposerHookKey('migrations', []);

        foreach ($migrations as $path) {
            if ($this->filesystem->isDirectory($hook->getPath().'/'.$path)) {
                $this->migrator->reset($this->realPath([$path], $hook->getPath().'/')->all());
            } else {
                $this->migrator->resetFiles($this->realPath([$path], $hook->getPath().'/')->all());
            }
        }
    }

    /**
     * Run seeders found for a specific hook.
     *
     * @param \Larapack\Hooks\Hook $hook
     */
    protected function seedHook(Hook $hook)
    {
        $folders = (array) $hook->getComposerHookKey('seeders', []);
        $basePath = $hook->getPath().'/';

        $this->runSeeders($folders, $basePath);
    }

    /**
     * Run unseeders found for a specific hook.
     *
     * @param \Larapack\Hooks\Hook $hook
     */
    protected function unseedHook(Hook $hook)
    {
        $folders = (array) $hook->getComposerHookKey('unseeders', []);
        $basePath = $hook->getPath().'/';

        $this->runSeeders($folders, $basePath);
    }

    /**
     * Publish assets found for a specific hook.
     *
     * @param \Larapack\Hooks\Hook $hook
     */
    protected function publishHook(Hook $hook, $force = false)
    {
        $folders = (array) $hook->getComposerHookKey('assets', []);
        $basePath = $hook->getPath().'/';

        $filesystem = $this->filesystem;
        foreach ($folders as $location => $publish) {
            $publishPath = base_path($publish);
            if (!$realLocation = realpath($basePath.$location)) {
                continue;
            }

            if ($filesystem->isDirectory($realLocation)) {
                $allFiles = collect($filesystem->allFiles($realLocation))->map(function ($file) use ($realLocation) {
                    return substr($file->getRealPath(), strlen($realLocation) + 1);
                });
            } else {
                $allFiles = collect([new \Symfony\Component\Finder\SplFileInfo(
                    $realLocation,
                    '',
                    basename($realLocation)
                )]);
            }

            $newFiles = $allFiles->filter(function ($filename) use ($publishPath, $filesystem) {
                return !$filesystem->exists($publishPath.'/'.$filename);
            });

            $updatedFiles = $allFiles->filter(function ($filename) use ($publishPath, $filesystem) {
                return $filesystem->exists($publishPath.'/'.$filename);
            });

            if (!$force && isset($this->tempDirectories[$hook->name])) {
                $tempLocation = $this->tempDirectories[$hook->name].'/'.$location;
                $updatedFiles = $updatedFiles
                    ->filter(function ($filename) use ($tempLocation, $publishPath, $filesystem) {
                        if (!$filesystem->exists($tempLocation.'/'.$filename)) {
                            return true;
                        }

                        return md5_file($tempLocation.'/'.$filename) ==
                               md5_file($publishPath.'/'.$filename);
                    });
            }

            $newFiles->merge($updatedFiles)
                ->each(function ($filename) use ($realLocation, $publishPath, $filesystem) {
                    if (!$filesystem->isDirectory($realLocation)) {
                        $directory = substr($publishPath, 0, -strlen(basename($publishPath)));

                        if (!$filesystem->isDirectory($directory)) {
                            $filesystem->makeDirectory($directory, 0755, true, true);
                        }

                        return $filesystem->copy($realLocation, $publishPath);
                    }

                    $directory = substr($publishPath.'/'.$filename, 0, -strlen(basename($filename)));

                    if (!$filesystem->isDirectory($directory)) {
                        $filesystem->makeDirectory($directory, 0755, true, true);
                    }

                    $filesystem->delete($publishPath.'/'.$filename);
                    $filesystem->copy(
                        $realLocation.'/'.$filename,
                        $publishPath.'/'.$filename
                    );
                });
        }
    }

    /**
     * Unpublish assets found for a specific hook.
     *
     * @param \Larapack\Hooks\Hook $hook
     */
    protected function unpublishHook(Hook $hook)
    {
        $folders = (array) $hook->getComposerHookKey('assets', []);
        $basePath = $hook->getPath().'/';

        $filesystem = $this->filesystem;
        foreach ($folders as $location => $publish) {
            $publishPath = base_path($publish);
            if (!$realLocation = realpath($basePath.$location)) {
                continue;
            }

            if ($filesystem->isDirectory($realLocation)) {
                $allFiles = collect($this->filesystem->allFiles($realLocation))
                    ->map(function ($file) use ($realLocation) {
                        return substr($file->getRealPath(), strlen($realLocation) + 1);
                    });
            } else {
                $allFiles = collect([new \Symfony\Component\Finder\SplFileInfo(
                    $realLocation,
                    '',
                    basename($realLocation)
                )]);
            }

            $existingFiles = $allFiles->filter(function ($filename) use ($publishPath, $filesystem) {
                if ($filesystem->isDirectory($publishPath)) {
                    return $filesystem->exists($publishPath.'/'.$filename);
                }

                return $filesystem->exists($publishPath);
            });

            $existingFiles->each(function ($filename) use ($publishPath, $filesystem) {
                if ($filesystem->isDirectory($publishPath)) {
                    return $filesystem->delete($publishPath.'/'.$filename);
                }

                $filesystem->delete($publishPath);
            });
        }
    }

    /**
     * Run seeder files.
     *
     * @param array  $folders
     * @param string $basePath
     */
    protected function runSeeders($folders, $basePath)
    {
        $filesystem = $this->filesystem;

        $this->realPath($folders, $basePath)
            ->each(function ($folder) use ($filesystem) {
                if ($filesystem->isDirectory($folder)) {
                    $files = $filesystem->files($folder);
                } else {
                    $files = [new \Symfony\Component\Finder\SplFileInfo(
                        $folder,
                        '',
                        basename($folder)
                    )];
                }

                collect($files)->filter(function ($file) {
                    return $file->getExtension() == 'php';
                })->each(function ($file) {
                    $class = substr($file->getFilename(), 0, -4);
                    require_once $file->getRealPath();

                    (new $class())->run();
                });
            });
    }

    /**
     * Get collection of realpath paths.
     *
     * @param array  $paths
     * @param string $basePath
     *
     * @return \Illuminate\Support\Collection
     */
    protected function realPath(array $paths, $basePath = '')
    {
        return collect($paths)->map(function ($path) use ($basePath) {
            return realpath($basePath.$path);
        })->filter(function ($path) {
            return $path;
        });
    }

    /**
     * Make temponary backup of hook.
     *
     * @param \Larapack\Hooks\Hook $hook
     *
     * @return void
     */
    protected function makeTemponaryBackup(Hook $hook)
    {
        $folder = $this->createTempFolder();
        $this->filesystem->copyDirectory($hook->getPath(), $folder);

        $this->tempDirectories[$hook->name] = $folder;
    }

    protected function createTempFolder()
    {
        $path = sys_get_temp_dir().'/hooks-'.uniqid();

        if ($this->filesystem->exists($path)) {
            return $this->createTempFolder();
        }

        return $path;
    }

    protected function clearTemponaryFiles()
    {
        foreach ($this->tempDirectories as $directory) {
            $this->filesystem->deleteDirectory($directory);
        }
    }
}

// TODO: MOVE!
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
