# Simple Container

A small PHP 5.3 dependency injection container

## Installation

```
composer require godruoyi/container

```

## Support

 - bind
 - make
 - call
 - singleton
 - extend

## Usage

Creating a container instance:

```php

use Godruoyi\Container\Container;

$app = new Container();

```

### Bind a abstract type to container

```php

interface Cache {}

class Redis implements Cache {}

$app->bind(Cache::class, new Redis);

$redis = $app[Cache::class];//The Redis Instance

```

### use Closure

```php

$app->bind(Cache::class, function () {
    return new Redis;
});

$redis = $app[Cache::class];//The Redis Instance

```

### Register A shared binding in the container

```php

$app->singleton('abstract', YourClass::class);

$one = $app['abstract'];
$two = $app['abstract'];

$one === $two; //true

```
When you register a shared binding in the container, you well get same object

### Extend a abstract use Closure

```php

$app->bind('abstract', YourClass::class);

$app->extend('abstract', function($instance, $container){
    $instance->hasModify = true;

    return $instance;
});

$app['abstract']->hasModify;//true

```

### Register an existing instance as shared in the container.

```php

$instance = new SomeClass();
$app->instance('instance', $instance);

$app['instance'] === $instance; //true

```

### Create any abstract type

```php

$instance = $app->mark(YourClass::class);
$instance = $app->mark(YourClass::class, array $params = []);

```

