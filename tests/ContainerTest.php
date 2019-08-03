<?php

namespace Tests;

use Godruoyi\Container\Container;
use Godruoyi\Container\ContainerInterface;
use PHPUnit\Framework\TestCase as BaseTestCase;
use Tests\Support\BookInterface;
use Tests\Support\Hongloumeng;

class ContainerTest extends BaseTestCase
{
    use Traits\CreatedContainer;

    public function testBasic()
    {
        $this->assertInstanceOf(ContainerInterface::class, $this->app);
        $this->assertInstanceOf(Container::class, $this->app);
    }

    public function testHas()
    {
        $this->app['key'] = 1;

        $this->assertTrue($this->app->has('key'));
        $this->assertFalse($this->app->has('not exists'));
    }

    public function testBound()
    {
        $this->app->bind('aaa', function () {
            return 'aaa';
        });

        $this->assertTrue($this->app->bound('aaa'));
        $this->assertFalse($this->app->bound('bbb'));
    }

    public function testAlias()
    {
        $this->app->alias(Support\Hongloumeng::class, 'aaa');

        $this->assertEquals($this->app->getAlias('aaa'), Support\Hongloumeng::class);
    }

    public function testGet()
    {
        $this->app['aaa'] = 1;

        $this->assertTrue($this->app->get('aaa') == 1);
    }

    public function testBind()
    {
        $this->app->bind(BookInterface::class, Hongloumeng::class);

        $this->assertEquals($this->app[BookInterface::class]->name(), 'hong lou meng');
    }

    public function testSingleton()
    {
        $this->app->singleton(BookInterface::class, function () {
            return new Hongloumeng();
        });

        $this->assertEquals($this->app->get(BookInterface::class)->name(), 'hong lou meng');
    }

    public function testExtend()
    {
        $this->app->singleton(BookInterface::class, function () {
            return new Hongloumeng();
        });

        $this->assertEquals($this->app->get(BookInterface::class)->name(), 'hong lou meng');

        $this->app->extend(BookInterface::class, function ($book) {
            return $book->resetName('Jiu yang shen gong');
        });

        $this->assertEquals($this->app->get(BookInterface::class)->name(), 'Jiu yang shen gong');
    }

    public function testCall()
    {
        $this->assertEquals($this->app->call(function () {
            return 'hello';
        }), 'hello');

        $this->assertEquals($this->app->call('\Tests\Support\Hongloumeng@name'), 'hong lou meng');
    }

    public function testInstance()
    {
        $this->app->instance(BookInterface::class, new Hongloumeng());

        $this->assertEquals($this->app->get(BookInterface::class)->name(), 'hong lou meng');
    }

    public function testMake()
    {
        $this->app->instance(BookInterface::class, new Hongloumeng());

        $this->assertInstanceOf(Hongloumeng::class, $this->app->make(BookInterface::class));
        $this->assertEquals($this->app->make(BookInterface::class)->name(), 'hong lou meng');
    }

    public function testResolved()
    {
        $this->app->instance(BookInterface::class, new Hongloumeng());

        $this->assertTrue($this->app->resolved(BookInterface::class));
    }
}
