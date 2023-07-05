<?php

/*
 * This file is part of the godruoyi/easy-container.
 *
 * (c) Godruoyi <g@godruoyi.com>
 *
 * This source file is subject to the MIT license that is bundled.
 */

namespace Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;
use Tests\Support\BookServiceProvider;

class ServiceProvideTest extends BaseTestCase
{
    public function test_basic()
    {
        $container = new \Godruoyi\Container\Container();

        $a = new BookServiceProvider();
        $a->register($container);

        $this->assertEquals($container['BookInterface']->name(), 'hong lou meng');
    }
}
