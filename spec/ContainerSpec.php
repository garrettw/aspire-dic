<?php

namespace spec\Aspire\DIC;

use Aspire\DIC\Container;
use Aspire\DIC\Config;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class ContainerSpec extends ObjectBehavior
{
    function it_is_initializable()
    {
        $this->shouldHaveType(Container::class);
    }

    function it_is_initializable_with_config(Config $config)
    {
        $this->beConstructedWith($config);
        $this->shouldHaveType(Container::class);
        $this->hasConfig()->shouldBe(true);
    }
}
