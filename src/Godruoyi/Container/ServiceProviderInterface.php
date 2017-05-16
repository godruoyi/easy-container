<?php

namespace Godruoyi\Container;

use Godruoyi\Container\Container;

/**
 * service provider interface.
 *
 * @author  Godruoyi
 */
interface ServiceProviderInterface
{
    /**
     * Registers services on the given container.
     *
     * This method should only be used to configure services and parameters.
     * It should not get services.
     *
     * @param Container $container A container instance
     */
    public function register(Container $container);
}