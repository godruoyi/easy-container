<?php

namespace Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;
use Tests\Support\BookInterface;
use Tests\Support\BookServiceProvider;

class ServiceProvideTest extends BaseTestCase
{
    public function testBasic()
    {
        $container = new \Godruoyi\Container\Container;

        (new BookServiceProvider)->register($container);

        $this->assertEquals($container[BookInterface::class]->name(), 'hong lou meng');
    }
}
