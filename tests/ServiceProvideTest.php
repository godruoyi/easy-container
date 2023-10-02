<?php

/*
 * This file is part of the godruoyi/easy-container.
 *
 * (c) Godruoyi <g@godruoyi.com>
 *
 * This source file is subject to the MIT license that is bundled.
 */

namespace Tests;

use Exception;
use Godruoyi\Container\Container;
use PHPUnit\Framework\TestCase as BaseTestCase;
use Tests\Support\BookInterface;
use Tests\Support\BookServiceProvider;

class ServiceProvideTest extends BaseTestCase
{
    /**
     * @throws Exception
     */
    public function test_basic()
    {
        $container = new Container();

        $a = new BookServiceProvider();
        $a->register($container);

        $this->assertEquals('Three Body', $container['book']->name());
        $this->assertEquals('Three Body', $container[BookInterface::class]->name());
    }
}
