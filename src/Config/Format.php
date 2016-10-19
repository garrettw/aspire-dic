<?php

namespace Aspire\DIC\Config;

interface Format
{
    public function __construct($path = '');
    public function load();
}
