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
use PHPUnit\Framework\TestCase;
use Tests\Support\BookInterface;
use Tests\Support\ThreeBody;

final class ContainerTest extends TestCase
{
    public function test_bound_in_binds()
    {
        $app = new Container();
        $app->bind('foo', function () {
            return 'bar';
        });

        $this->assertTrue($app->bound('foo'));
        $this->assertFalse($app->bound('bar'));
    }

    public function test_bound_in_instances()
    {
        $app = new Container();
        $app->instance('foo', function () {
            return 'bar';
        });

        $this->assertTrue($app->bound('foo'));
        $this->assertFalse($app->bound('bar'));
    }

    public function test_bound_in_alias()
    {
        $app = new Container();
        $app->alias('foo', 'bar');

        $this->assertTrue($app->bound('bar'));
        $this->assertFalse($app->bound('foo'));
    }

    /**
     * @throws Exception
     */
    public function test_alias()
    {
        $app = new Container();
        $app->alias('foo', 'bar');

        $this->assertEquals('foo', $app->getAlias('bar'));
    }

    public function test_has()
    {
        $app = new Container();
        $app['key'] = 1;

        $this->assertTrue($app->has('key'));
        $this->assertFalse($app->has('not exists'));
    }

    public function test_get()
    {
        $app = new Container();
        $app['aaa'] = 1;

        $this->assertTrue($app->get('aaa') == 1);
    }

    public function test_get_not_exists()
    {
        $app = new Container();
        $app['aaa'] = 1;

        $this->assertTrue($app->get('aaa') == 1);

        // If get an not exists key, will throw an exception.
        $this->expectException(Exception::class);
        $app->get('bbb');
    }

    /**
     * @throws Exception
     */
    public function test_bind()
    {
        $app = new Container();
        $app->bind('book', ThreeBody::class);

        $a = $app['book'];
        $b = $app['book'];

        $this->assertEquals('Three Body', $a->name());
        $this->assertEquals('Three Body', $b->name());
        $this->assertTrue($a !== $b);
    }

    /**
     * @throws Exception
     */
    public function test_bind_share()
    {
        $app = new Container();
        $app->bind('book', ThreeBody::class, true);

        $a = $app['book'];
        $b = $app['book'];

        $this->assertEquals('Three Body', $a->name());
        $this->assertEquals('Three Body', $b->name());
        $this->assertTrue($a === $b);
    }

    /**
     * @throws Exception
     */
    public function test_bind_with_alias()
    {
        $app = new Container();
        $app->bind([
            'Interface' => 'Alias',
        ], ThreeBody::class, true);

        $a = $app['Interface'];
        $b = $app['Alias'];

        $this->assertEquals('Three Body', $a->name());
        $this->assertTrue($app->isAlias('Alias'));
        $this->assertTrue($a === $b);
    }

    /**
     * @throws Exception
     */
    public function test_bind_concrete_null()
    {
        $app = new Container();
        $app->bind('a');

        $this->assertTrue(! $app->isAlias('a'));
        $this->assertTrue($app->bound('a'));
        $this->assertTrue($app->getConcrete('not_exists') === 'not_exists');
    }

    /**
     * @throws Exception
     */
    public function test_bind_closure()
    {
        $app = new Container();
        $app->bind('a', function () {
            return 1;
        });

        $this->assertTrue(! $app->isAlias('a'));
        $this->assertTrue($app->bound('a'));
        $this->assertEquals(1, $app['a']);
    }

    /**
     * @throws Exception
     */
    public function test_bind_resolved()
    {
        $app = new Container();
        $app->bind('a', function () {
            return 1;
        });

        $app->bind('a', function () {
            return 2;
        });

        $this->assertTrue(! $app->isAlias('a'));
        $this->assertTrue($app->bound('a'));
        $this->assertEquals(2, $app['a']);
    }

    /**
     * @throws Exception
     */
    public function test_bind_if()
    {
        $app = new Container();
        $app->bindIf('a', function () {
            return 1;
        });

        // Don't take effect.
        $app->bindIf('a', function () {
            return '2';
        });

        $this->assertTrue(! $app->isAlias('a'));
        $this->assertTrue($app->bound('a'));
        $this->assertEquals(1, $app['a']);
    }

    public function test_singleton()
    {
        $app = new Container();
        $app->singleton('BookInterface', function () {
            return new ThreeBody();
        });

        $this->assertEquals('Three Body', $app->get('BookInterface')->name());
    }

    public function test_extend()
    {
        $app = new Container();
        $app->singleton('BookInterface', function () {
            return new ThreeBody();
        });

        $this->assertEquals('Three Body', $app->get('BookInterface')->name());

        $app->extend('BookInterface', function (ThreeBody $book) {
            $book->name = 'Harry Potter';

            return $book;
        });

        $this->assertEquals('Harry Potter', $app->get('BookInterface')->name());
    }

    public function test_call()
    {
        $app = new Container();
        $this->assertEquals('hello', $app->call(function () {
            return 'hello';
        }));

        $this->assertEquals('Three Body', $app->call('\Tests\Support\ThreeBody@name'));
    }

    public function test_call_need_parameters()
    {
        $app = new Container();
        $this->assertEquals('Three Body', $app->call(function (ThreeBody $book) {
            return $book->name();
        }));
    }

    public function test_call_need_parameters2()
    {
        $app = new Container();
        $this->assertEquals(2, $app->call(function ($a, $b = 1) {
            return $a + $b;
        }, ['a' => 1]));
    }

    public function test_call_need_parameters3()
    {
        $app = new Container();

        $this->assertEquals(3, $app->call(function ($a, $b = 1) {
            return $a + $b;
        }, ['a' => 1, 'b' => 2]));
    }

    public function test_instance()
    {
        $app = new Container();
        $app->instance('BookInterface', new ThreeBody);

        $this->assertEquals('Three Body', $app->get('BookInterface')->name());
    }

    public function test_make()
    {
        $app = new Container();
        $app->instance('BookInterface', new ThreeBody);

        $this->assertInstanceOf('Tests\Support\ThreeBody', $app->make('BookInterface'));
        $this->assertEquals('Three Body', $app->make('BookInterface')->name());
    }

    public function test_make_with_auto_injection()
    {
        $app = new Container();

        $app->instance(BookInterface::class, new ThreeBody);
        $app->bind('a', ThreeBody::class);

        $a = $app->make('a');

        $this->assertInstanceOf('Tests\Support\ThreeBody', $a);
        $this->assertEquals($a->name(), 'Three Body');
    }

    public function test_get_class()
    {
        $x = new \ReflectionFunction(function (ThreeBody $a, $b) {
        });

        $ps = $x->getParameters();

        $this->assertCount(2, $ps);
    }

    public function test_resolved()
    {
        $app = new Container();
        $app->instance('BookInterface', new ThreeBody);

        $this->assertTrue($app->resolved('BookInterface'));
    }
}
