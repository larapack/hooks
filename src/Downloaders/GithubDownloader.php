<?php

namespace Larapack\Hooks\Downloaders;

use Illuminate\Filesystem\Filesystem;
use Larapack\Hooks\Hooks;
use RuntimeException;
use ZipArchive;

class GithubDownloader implements DownloaderInterface
{
    protected $filesystem;

    protected $location;

    protected $hooks;

    public function __construct(Filesystem $filesystem, Hooks $hooks)
    {
        $this->filesystem = $filesystem;
        $this->hooks = $hooks;
    }

    public function download(array $remote, $version = null)
    {
        $token = config('hooks.github.token');

        $this->location = tempnam(sys_get_temp_dir(), '');

        if ($this->filesystem->exists($this->location)) {
            $this->filesystem->delete($this->location);
        }

        $this->filesystem->makeDirectory($this->location);

        $context = stream_context_create(['http' => [
            'header' => 'User-Agent: sistecs',
        ]]);

        if (is_null($version)) {
            $version = $remote['version'] ? $remote['version'] : 'master';
        }

        $url = "https://api.github.com/repos/{$remote['github']['vendor']}/{$remote['github']['project']}/zipball/{$version}";
        if (!is_null($token)) {
            $url .= "?access_token={$token}";
        }

        $remoteFile = file_get_contents($url, false, $context);
        $localFile = fopen($this->location.'/master.zip', 'w');
        fwrite($localFile, $remoteFile);
        fclose($localFile);

        $zip = new ZipArchive();
        $res = $zip->open($this->location.'/master.zip');

        if ($res === true) {
            $zip->extractTo($this->location.'/master');
            $zip->close();
        } else {
            throw new RuntimeException("Could not unzip file [{$this->location}].");
        }
    }

    public function output($path)
    {
        $directories = $this->filesystem->directories($this->location.'/master');

        $this->filesystem->moveDirectory($directories[0], $path);
    }
}
