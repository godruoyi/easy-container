<?php

/*
 * This file is part of the godruoyi/easy-container.
 *
 * (c) Godruoyi <g@godruoyi.com>
 *
 * This source file is subject to the MIT license that is bundled.
 */

namespace Tests\Support;

use Exception;
use Godruoyi\Container\Container;
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
     * @param  Container  $container A container instance
     *
     * @throws Exception
     */
    public function register(ContainerInterface $container): void
    {
        $container->bind(BookInterface::class, function ($app) {
            return $app->make(ThreeBody::class);
        });

        $container->alias(BookInterface::class, 'book');
    }
}
