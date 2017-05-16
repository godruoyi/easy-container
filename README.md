# Laravel-Container

A small PHP 5.3 dependency injection container from Laravel Container

## Installation

```
composer require godruoyi/laravel-container

```

## Usage

Creating a container

```php

use Godruoyi\Container\Container;

$app = new Container();

```

### Bind a abstract type to container

```php

$app->bind('abstract', 'Your\Class::class');
$instance = $app['abstract'];

//use alias
$app->bind(['abstract' => 'alias'], 'Your\Class::class');
$instance = $app['alias'];

//use Closure
$app->bind(['abstract' => 'alias'], function(){
	return new YourClass();
});
$instance = $app['alias'];

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

$app->bind('abstract', new Class());

$app['abstract'] === $app['abstract']; //true

```

### Create any abstract type

```php

$instance = $app->mark(YourClass::class);
$instance = $app->mark(YourClass::class, array $params = []);

```

