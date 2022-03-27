<?php

namespace Tests\Support;

use Godruoyi\Container\ContainerInterface;
use Godruoyi\Container\ServiceProviderInterface;

class BookServiceProvider implements ServiceProviderInterface
{
    /**
     * Registers services on the given container.
     *
     * This method should only be used to configure services and parameters.
     * It should not get services.
     *
     * @param Container $container A container instance
     */
    public function register(ContainerInterface $container)
    {
        $container->bind('BookInterface', function ($app) {
            return $app->make('Tests\Support\Hongloumeng');
        });
    }
}
