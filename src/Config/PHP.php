<?php

namespace Aspire\DIC\Config;

use Aspire\DIC\Exception\ContainerException;

class PHP implements Format
{
    private $path = '';

    public function __construct($path = '')
    {
        $this->path = $path;
    }

    public function load()
    {
        $data = include $path;
        if (!\is_array($data)) {
            throw new ContainerException('Not a valid config file: ' . $path);
        }
        return $data;
    }
}
