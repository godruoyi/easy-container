<?php

namespace Tests\Traits;

use Godruoyi\Container\Container;

trait CreatedContainer
{
    /**
     * The app instance.
     *
     * @var \Godruoyi\Container\ContainerInterface
     */
    protected $app;

    public function setUp()
    {
        $this->app = new Container();
    }
}
