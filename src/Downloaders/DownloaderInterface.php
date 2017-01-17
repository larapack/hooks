<?php

namespace Larapack\Hooks\Downloaders;

interface DownloaderInterface
{
    public function download(array $remote, $version = null);

    public function output($path);
}
