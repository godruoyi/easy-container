<?php

namespace Tests;

use Godruoyi\Container\Container;
use Godruoyi\Container\ContainerInterface;
use PHPUnit\Framework\TestCase as BaseTestCase;
use Tests\Support\BookInterface;
use Tests\Support\Hongloumeng;

class ContainerTest extends BaseTestCase
{
    public function test_basic()
    {
        $app = new Container();

        $this->assertInstanceOf(ContainerInterface::class, $app);
        $this->assertInstanceOf(Container::class, $app);
    }

    public function test_has()
    {
        $app = new Container();
        $app['key'] = 1;

        $this->assertTrue($app->has('key'));
        $this->assertFalse($app->has('not exists'));
    }

    public function test_bound()
    {
        $app = new Container();
        $app->bind('aaa', function () {
            return 'aaa';
        });

        $this->assertTrue($app->bound('aaa'));
        $this->assertFalse($app->bound('bbb'));
    }

    public function test_alias()
    {
        $app = new Container();
        $app->alias(Support\Hongloumeng::class, 'aaa');

        $this->assertEquals($app->getAlias('aaa'), Support\Hongloumeng::class);
    }

    public function test_get()
    {
        $app = new Container();
        $app['aaa'] = 1;

        $this->assertTrue($app->get('aaa') == 1);
    }

    public function test_bind()
    {
        $app = new Container();
        $app->bind(BookInterface::class, Hongloumeng::class);

        $this->assertEquals($app[BookInterface::class]->name(), 'hong lou meng');
    }

    public function test_singleton()
    {
        $app = new Container();
        $app->singleton(BookInterface::class, function () {
            return new Hongloumeng();
        });

        $this->assertEquals($app->get(BookInterface::class)->name(), 'hong lou meng');
    }

    public function test_extend()
    {
        $app = new Container();
        $app->singleton(BookInterface::class, function () {
            return new Hongloumeng();
        });

        $this->assertEquals($app->get(BookInterface::class)->name(), 'hong lou meng');

        $app->extend(BookInterface::class, function ($book) {
            return $book->resetName('Jiu yang shen gong');
        });

        $this->assertEquals($app->get(BookInterface::class)->name(), 'Jiu yang shen gong');
    }

    public function test_call()
    {
        $app = new Container();
        $this->assertEquals($app->call(function () {
            return 'hello';
        }), 'hello');

        $this->assertEquals($app->call('\Tests\Support\Hongloumeng@name'), 'hong lou meng');
    }

    public function test_instance()
    {
        $app = new Container();
        $app->instance(BookInterface::class, new Hongloumeng());

        $this->assertEquals($app->get(BookInterface::class)->name(), 'hong lou meng');
    }

    public function test_make()
    {
        $app = new Container();
        $app->instance(BookInterface::class, new Hongloumeng());

        $this->assertInstanceOf(Hongloumeng::class, $app->make(BookInterface::class));
        $this->assertEquals($app->make(BookInterface::class)->name(), 'hong lou meng');
    }

    public function test_resolved()
    {
        $app = new Container();
        $app->instance(BookInterface::class, new Hongloumeng());

        $this->assertTrue($app->resolved(BookInterface::class));
    }
}
