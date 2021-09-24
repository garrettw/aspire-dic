<?php

namespace Aspire\DIC\Config;

use Aspire\DIC\Exception\ContainerException;

class JSONFile implements Format
{
    private $path;

    public function __construct($path)
    {
        $this->path = $path ?: '';
    }

    public function load()
    {
        $path = dirname(realpath($this->path));
        $json = str_replace('__DIR__', $path, file_get_contents($this->path));

        $data = json_decode($json, true);
        if (!is_array($data)) {
            throw new ContainerException('Could not decode json: ' . json_last_error_msg());
        }
        return $data;
    }
}
