<?php

/**
 * @description Aspire's Inversion-of-Control dependency injection container, based on Dice
 *
 * @author      Tom Butler tom@r.je
 * @author      Garrett Whitehorn http://garrettw.net/
 * @license     http://www.opensource.org/licenses/bsd-license.php  BSD License
 */

namespace Aspire\DIC;

class Container
{
    private $config = null;

    public function __construct(Config $config = null)
    {
        $this->config = $config;
    }

    public function hasConfig()
    {
        return isset($this->config);
    }
}
