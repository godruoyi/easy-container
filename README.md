<h1 align="center">A simple dependency injecting container from laravel.</h1>

<p align="center">
    <a href="https://github.com/godruoyi/easy-container"><img src="https://github.com/godruoyi/easy-container/actions/workflows/php.yml/badge.svg?branch=master" alt="styleci passed"></a>
    <a href="https://packagist.org/packages/godruoyi/easy-container"><img src="https://poser.pugx.org/godruoyi/easy-container/v/stable.svg" alt="Latest Stable Version"></a>
    <a href="https://packagist.org/packages/godruoyi/easy-container"><img src="https://poser.pugx.org/godruoyi/easy-container/downloads" alt="Total Downloads"></a>
    <a href="https://packagist.org/packages/godruoyi/easy-container"><img src="https://poser.pugx.org/godruoyi/easy-container/license" alt="License"></a>
</p>

# Why

[中文文档](https://github.com/godruoyi/easy-container/blob/master/README_zh-CN.md)

Currently more popular `php` container:

 - [Pimple](https://pimple.symfony.com/)
 - [Laravel Container](https://github.com/illuminate/container)
 - [Other Dependency-injection Container](https://github.com/ziadoz/awesome-php#dependency-injection)

`Pimple` is a simple and excellent `php 5.3` container, which is also the most used service container, and the installed capacity of [packagist](https://packagist.org/packages/pimple/pimple) is also up to `1000W+` .But `Pimple` just a simple service container that does not support many features such as:

```php
class Cache
{
    public function __construct(Config $config){}
}

class Config
{
}

// not support
$cache = $container->make('Cache');
```

> Pimple Does not support the automatic injection of dependency parameters, when you need to rely on other objects object, you can only instantiate the required parameters.

`Laravel Container` is the most full-featured service container, including auto-injection, load-loading, alias, TAG, and so so. But the official does not recommend using the component in non-laravel project.

> If you have noticed the `composer.json` file under that component，You will find that he depends on the [illuminate/contracts](https://github.com/illuminate/contracts) component.([see also](https://github.com/laravel/framework/issues/21435))

Based on this, [easy-container](https://github.com/godruoyi/easy-container) was born, and the project code relied heavily on [Laravel Container](https://github.com/illuminate/container) :smile: :smile: . You can use it like a `Laravel Container` container.

# Install

🐝 Now, we support most PHP versions, which you can view [here](https://github.com/godruoyi/easy-container/actions/workflows/php.yml).

```shell
composer require godruoyi/easy-container
```

# Use

You can get more help with [container usage](https://laravel.com/docs/5.5/container) at [laravel.com](https://laravel.com).

Initialize the container.

```php
$app = new Godruoyi\Container\Container;
```

> The following documents support from [laravel.com](https://laravel.com/docs/5.5/container), reproduced please indicate the source.

#### Simple Bindings

We can register a binding using the `bind` method, passing the class or interface name that we wish to register along with a `Closure` that returns an instance of the class:

```php
$app->bind('HelpSpot\API', function ($app) {
    return new HelpSpot\API($app->make('HttpClient'));
});
```

> Note,All anonymous functions accept the service container instance as a parameter.

#### Binding A Singleton

The `singleton` method binds a class or interface into the container that should only be resolved one time. Once a singleton binding is resolved, the same object instance will be returned on subsequent calls into the container:

```php
$app->singleton('HelpSpot\API', function ($app) {
    return new HelpSpot\API($app->make('HttpClient'));
});
```

> Each time you call `$app['HelpSpot\API']` will return the same object.

#### Binding A Singleton

The `singleton` method binds a class or interface into the container that should only be resolved one time. Once a singleton binding is resolved, the same object instance will be returned on subsequent calls into the container:

    $api = new HelpSpot\API(new HttpClient);

    $app->instance('HelpSpot\API', $api);

### Binding Interfaces To Implementations

A very powerful feature of the service container is its ability to bind an interface to a given implementation. For example, let's assume we have an `EventPusher` interface and a `RedisEventPusher` implementation. Once we have coded our `RedisEventPusher` implementation of this interface, we can register it with the service container like so:

    $app->bind(
        'App\Contracts\EventPusher',
        'App\Services\RedisEventPusher'
    );

This statement tells the container that it should inject the `RedisEventPusher` when a class needs an implementation of `EventPusher`. Now we can type-hint the `EventPusher` interface in a constructor, or any other location where dependencies are injected by the service container:

    use App\Contracts\EventPusher;

    /**
     * Create a new instance of the class, which will be injected into the App\Services\RedisEventPusher instance.
     *
     * @param  EventPusher  $pusher
     * @return void
     */
    public function __construct(EventPusher $pusher)
    {
        $this->pusher = $pusher;
    }

## Resolving

#### The `make` Method

You may use the `make` method to resolve a class instance out of the container(regardless of what type of parameter the object needs). The `make` method accepts the name of the class or interface you wish to resolve:

    $api = $app->make('HelpSpot\API');

The `mark` method is the most important method I think of,You can simply use the "type prompt" way to add dependencies,the container will automatically parse all the parameters you need.

```php

// Automatically parses the dependencies required by the UserController constructor
$userController = $app->make(UserController::class);

class UserController
{
    public function __construct(UserRepository $users, HttpClient $client, $other = 'default')
    {
    }
}

```

## PSR-11

Laravel's service container implements the PSR-11 interface. Therefore, you may type-hint the PSR-11 container interface to obtain an instance of the Laravel container:

    use Psr\Container\ContainerInterface;

    $service = $app->get('Service');

# LISTEN

MIT

# Thanks

[laravel-china](https://laravel.com)
