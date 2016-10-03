<?php

namespace spec\Aspire\DIC;

use Aspire\DIC\Config;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class ConfigSpec extends ObjectBehavior
{
    function it_is_initializable()
    {
        $this->shouldHaveType(Config::class);
    }

    function it_is_initializable_with_path()
    {
        $this->beConstructedWith('valid/path');
        $this->shouldHaveType(Config::class);
    }
}
