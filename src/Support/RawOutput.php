<?php

namespace Larapack\Hooks\Support;

use Symfony\Component\Console\Output\Output;

class RawOutput extends Output
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
