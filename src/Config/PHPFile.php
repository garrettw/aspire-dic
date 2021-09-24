<?php

namespace Aspire\DIC\Config;

use Aspire\DIC\Exception\ContainerException;

class PHPFile implements Format
{
    private $path;

    public function __construct($path)
    {
        $this->path = $path ?: '';
    }

    public function load()
    {
        $data = @include $this->path;
        if (!\is_array($data)) {
            throw new ContainerException('Not a valid config file: ' . $this->path);
        }
        return $data;
    }
}
