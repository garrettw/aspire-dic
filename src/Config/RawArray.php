<?php

namespace Aspire\DIC\Config;

use Aspire\DIC\Exception\ContainerException;

class RawArray implements Format
{
    private $path;

    public function __construct($path)
    {
        $this->path = $path ?: [];
    }

    public function load()
    {
        $data = $this->path;
        if (!\is_array($data)) {
            throw new ContainerException('Not a valid config array');
        }
        return $data;
    }
}
