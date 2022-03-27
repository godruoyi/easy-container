<h1 align="center">一个简单的 `php 5.3` 依赖注入容器</h1>

<p align="center">
    <a href="https://github.com/godruoyi/easy-container"><img src="https://github.com/godruoyi/easy-container/actions/workflows/php.yml/badge.svg?branch=master" alt="styleci passed"></a>
    <a href="https://packagist.org/packages/godruoyi/easy-container"><img src="https://poser.pugx.org/godruoyi/easy-container/v/stable.svg" alt="Latest Stable Version"></a>
    <a href="https://packagist.org/packages/godruoyi/easy-container"><img src="https://poser.pugx.org/godruoyi/easy-container/v/unstable.svg" alt="Latest Unstable Version"></a>
    <a href="https://packagist.org/packages/godruoyi/easy-container"><img src="https://poser.pugx.org/godruoyi/easy-container/downloads" alt="Total Downloads"></a>
    <a href="https://packagist.org/packages/godruoyi/easy-container"><img src="https://poser.pugx.org/godruoyi/easy-container/license" alt="License"></a>
</p>

# Why

目前比较流行的 `PHP` 容器：

 - [Pimple](https://pimple.symfony.com/)
 - [Laravel Container](https://github.com/illuminate/container)
 - [其他依赖注入容器](https://github.com/ziadoz/awesome-php#dependency-injection)

`Pimple` 是一个简单优秀的 `php 5.3` 容器，也是目前用得最多的服务容器，在 [packagist](https://packagist.org/packages/pimple/pimple) 的安装量也达到 `1000 w+`。但是 `Pimple` 只是一个简单的服务容器，不支持很多特性如：

```php
class Cache
{
    public function __construct(Config $config){}
}

class Config
{
}

// 不支持
$cache = $container->make('Cache');
```

> Pimple 不支持自动注入依赖参数，当你需要的对象依赖其他对象时，你只能依次实例化所需参数。

`Laravel Container` 是目前功能最全的服务容器了，支持的功能也比较全面，包括自动注入、赖加载、别名、TAG等。但是官方不推荐在非 `laravel` 项目中使用该组件。

> 如果你有留意该组件下的 `composer.json` 文件，你会发现他依赖 [illuminate/contracts](https://github.com/illuminate/contracts) 组件。（[参见](https://github.com/laravel/framework/issues/21435)）

基于此，诞生了 [easy-container](https://github.com/godruoyi/easy-container)，该项目代码大部分依赖于 [Laravel Container](https://github.com/illuminate/container) :smile:。你可以像使用 `Laravel Container` 容器般来使用它。

# 安装

```shell
composer require godruoyi/easy-container
```

# 使用

你可以前往 [Laravel-china](https://laravel-china.org) 获取更多关于 [容器使用](https://d.laravel-china.org/docs/5.5/container) 的帮助。

初始化容器

```php

$app = new Godruoyi\Container\Container;

```

> 以下文档支持来自 [laravel-china](https://d.laravel-china.org/docs/5.5/container)，转载请注明出处。

#### 简单绑定

可以通过 `bind` 方法注册绑定，传递我们想要注册的类或接口名称再返回类的实例的 `Closure` ：

```php
$app->bind('HelpSpot\API', function ($app) {
    return new HelpSpot\API($app->make('HttpClient'));
});
```

> 注意，所有匿名函数都接受服务容器实例作为参数。

#### 绑定一个单例

`singleton` 方法将类或接口绑定到只能解析一次的容器中。绑定的单例被解析后，相同的对象实例会在随后的调用中返回到容器中：

```php
$app->singleton('HelpSpot\API', function ($app) {
    return new HelpSpot\API($app->make('HttpClient'));
});
```

> 每次调用 `$app['HelpSpot\API']` 都将返回统一对象。

#### 绑定实例

你也可以使用 `instance` 方法将现有对象实例绑定到容器中。给定的实例会始终在随后的调用中返回到容器中：

    $api = new HelpSpot\API(new HttpClient);

    $app->instance('HelpSpot\API', $api);

### 绑定接口到实现

服务容器有一个强大的功能，就是将接口绑定到给定实现。例如，如果我们有一个 `EventPusher` 接口和一个 `RedisEventPusher` 实现。编写完接口的 `RedisEventPusher` 实现后，我们就可以在服务容器中注册它，像这样：

    $app->bind(
        'App\Contracts\EventPusher',
        'App\Services\RedisEventPusher'
    );

这么做相当于告诉容器：当一个类需要实现 `EventPusher` 时，应该注入 `RedisEventPusher`。现在我们就可以在构造函数或者任何其他通过服务容器注入依赖项的地方使用类型提示注入 `EventPusher` 接口：

    use App\Contracts\EventPusher;

    /**
     * 创建一个新的类实例，此处将注入 App\Services\RedisEventPusher 的实例。
     *
     * @param  EventPusher  $pusher
     * @return void
     */
    public function __construct(EventPusher $pusher)
    {
        $this->pusher = $pusher;
    }

## 解析

#### `make` 方法

你可以使用 `make` 方法将容器中的类实例解析出来 (无论该对象需要什么类型的参数)。`make` 方法接受要解析的类或接口的名称：

    $api = $app->make('HelpSpot\API');

`mark` 方法是我认为最重要的方法，你可以简单地使用「类型提示」的方式在由容器解析的类的构造函数中添加依赖项，容器将自动解析你所需要的一切参数。

```php

// 自动解析UserController构造函数所需的依赖
$userController = $app->make(UserController::class);

class UserController
{
    public function __construct(UserRepository $users, HttpClient $client, $other = 'default')
    {
    }
}

```

## PSR-11

Laravel 的服务容器实现了 [PSR-11](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-11-container.md) 接口。因此，你可以对 PSR-11容器接口类型提示来获取 Laravel 容器的实例：

    use Psr\Container\ContainerInterface;

    $service = $app->get('Service');

# LISTEN

MIT

# Thanks

[laravel-china](https://laravel-china.org)
