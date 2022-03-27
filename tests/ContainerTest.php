<?php

namespace Tests;

use Closure;
use Godruoyi\Container\Container;
use Godruoyi\Container\ContainerInterface;
use PHPUnit\Framework\TestCase as BaseTestCase;
use Tests\Support\BookInterface;
use Tests\Support\Hongloumeng;
use Tests\Support\ThreeBody;

class ContainerTest extends BaseTestCase
{
    public function test_basic()
    {
        $app = new Container();

        $this->assertInstanceOf('Godruoyi\Container\Container', $app);
    }

    public function test_bound_in_binds()
    {
        $app = new Container();
        $app->bind('aaa', function () {
            return 'aaa';
        });

        $this->assertTrue($app->bound('aaa'));
        $this->assertFalse($app->bound('bbb'));
    }

    public function test_bound_in_instances()
    {
        $app = new Container();
        $app->instance('aaa', function () {
            return 'aaa';
        });

        $this->assertTrue($app->bound('aaa'));
        $this->assertFalse($app->bound('bbb'));
    }

    public function test_bound_in_alias()
    {
        $app = new Container();
        $app->alias('bbb', 'aaa');

        $this->assertTrue($app->bound('aaa'));
        $this->assertFalse($app->bound('bbb'));
    }

    public function test_alias()
    {
        $app = new Container();
        $app->alias('Tests\Support\Hongloumeng', 'aaa');

        $this->assertEquals($app->getAlias('aaa'), 'Tests\Support\Hongloumeng');
    }

    public function test_has()
    {
        $app = new Container();
        $app['key'] = 1;

        $this->assertTrue($app->has('key'));
        $this->assertFalse($app->has('not exists'));
        $this->assertEquals(1, $app['key']);
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
    }

    public function test_bind()
    {
        $app = new Container();
        $app->bind('BookInterface', 'Tests\Support\Hongloumeng');

        $a = $app['BookInterface'];
        $b = $app['BookInterface'];

        $this->assertEquals($a->name(), 'hong lou meng');
        $this->assertEquals($b->name(), 'hong lou meng');
        $this->assertTrue($a !== $b);
    }

    public function test_bind_share()
    {
        $app = new Container();
        $app->bind('BookInterface', 'Tests\Support\Hongloumeng', true);

        $a = $app['BookInterface'];
        $b = $app['BookInterface'];

        $this->assertEquals($a->name(), 'hong lou meng');
        $this->assertEquals($b->name(), 'hong lou meng');
        $this->assertTrue($a === $b);
    }

    public function test_bind_with_alias()
    {
        $app = new Container();
        $app->bind([
            'Interface' => 'Alias',
        ], 'Tests\Support\Hongloumeng', true);

        $a = $app['Interface'];
        $b = $app['Alias'];

        $this->assertEquals($a->name(), 'hong lou meng');
        $this->assertTrue($app->isAlias('Alias'));
        $this->assertTrue($a === $b);
    }

    public function test_bind_concrete_null()
    {
        $app = new Container();
        $app->bind('a');

        $this->assertTrue(!$app->isAlias('a'));
        $this->assertTrue($app->bound('a'));
        $this->assertTrue($app->getConcrete('not_exists') === 'not_exists');
    }

    public function test_bind_closure()
    {
        $app = new Container();
        $app->bind('a', function () {
            return 1;
        });

        $this->assertTrue(!$app->isAlias('a'));
        $this->assertTrue($app->bound('a'));
        $this->assertEquals(1, $app['a']);
    }

    public function test_bind_resolved()
    {
        $app = new Container();
        $app->bind('a', function () {
            return 1;
        });

        $app->bind('a', function () {
            return 2;
        });

        $this->assertTrue(!$app->isAlias('a'));
        $this->assertTrue($app->bound('a'));
        $this->assertEquals(2, $app['a']);
    }

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

        $this->assertTrue(!$app->isAlias('a'));
        $this->assertTrue($app->bound('a'));
        $this->assertEquals(1, $app['a']);
    }

    public function test_singleton()
    {
        $app = new Container();
        $app->singleton('BookInterface', function () {
            return new Hongloumeng();
        });

        $this->assertEquals($app->get('BookInterface')->name(), 'hong lou meng');
    }

    public function test_extend()
    {
        $app = new Container();
        $app->singleton('BookInterface', function () {
            return new Hongloumeng();
        });

        $this->assertEquals($app->get('BookInterface')->name(), 'hong lou meng');

        $app->extend('BookInterface', function ($book) {
            return $book->resetName('Jiu yang shen gong');
        });

        $this->assertEquals($app->get('BookInterface')->name(), 'Jiu yang shen gong');
    }

    public function test_call()
    {
        $app = new Container();
        $this->assertEquals($app->call(function () {
            return 'hello';
        }), 'hello');

        $this->assertEquals($app->call('\Tests\Support\Hongloumeng@name'), 'hong lou meng');
    }

    public function test_call_need_parameters()
    {
        $app = new Container();
        $this->assertEquals($app->call(function (Hongloumeng $book) {
            return $book->name();
        }), 'hong lou meng');
    }

    public function test_call_need_parameters2()
    {
        $app = new Container();
        $this->assertEquals($app->call(function ($a, $b = 1) {
            return $a + $b;
        }, ['a' => 1]), 2);
    }

    public function test_call_need_parameters3()
    {
        $app = new Container();

        $this->assertEquals($app->call(function ($a, $b = 1) {
            return $a + $b;
        }, ['a' => 1, 'b' => 2]), 3);
    }

    public function test_instance()
    {
        $app = new Container();
        $app->instance('BookInterface', new Hongloumeng());

        $this->assertEquals($app->get('BookInterface')->name(), 'hong lou meng');
    }

    public function test_make()
    {
        $app = new Container();
        $app->instance('BookInterface', new Hongloumeng());

        $this->assertInstanceOf('Tests\Support\Hongloumeng', $app->make('BookInterface'));
        $this->assertEquals($app->make('BookInterface')->name(), 'hong lou meng');
    }

    public function test_make_with_auto_injection()
    {
        $app = new Container();

        $app->instance('Tests\Support\BookInterface', new Hongloumeng());
        $app->bind('a', 'Tests\Support\ThreeBody');

        $a = $app->make('a');

        $this->assertInstanceOf('Tests\Support\ThreeBody', $a);
        $this->assertInstanceOf('Tests\Support\Hongloumeng', $a->book);
        $this->assertEquals($a->getName(), 'hong lou meng');
    }

    public function test_get_class()
    {
        $x = new \ReflectionFunction(function (Hongloumeng $a, $b) {
        });

        $ps = $x->getParameters();

        $this->assertTrue(is_array($ps));
        $this->assertCount(2, $ps);
    }

    public function test_resolved()
    {
        $app = new Container();
        $app->instance('BookInterface', new Hongloumeng());

        $this->assertTrue($app->resolved('BookInterface'));
    }
}
