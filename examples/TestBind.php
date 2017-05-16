<?php

require __DIR__ . '/../vendor/autoload.php';

use Godruoyi\Container\Container;

$app = new Container();


class Test
{
	public $name;

	public function __construct($name = 'hello')
	{
		$this->name = $name;
	}
}

$app->bind('abstract', 'Test');
var_dump($app['abstract']->name);//hello


$app->bind('abstract', function(){
	return new Test('World');
});
var_dump($app['abstract']->name);//World

$app->bind(['Abstract' => 'alias'], function(){
	return new Test('alias');
});
var_dump($app['alias']->name);//alias


class Test2
{
	public $test;

	public function __construct(Test $test)
	{
		$this->test = $test;
	}
}

$app->bind('abstract', 'Test2');
var_dump($app['abstract']->test->name);//hello

$app->extend('abstract', function($instance, $container) {
	$instance->test->name = 'Has modify';
	return $instance;
});
var_dump($app['abstract']->test->name);//Has modify