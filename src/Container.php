<?php

/*
 * This file is part of the godruoyi/easy-container.
 *
 * (c) Godruoyi <g@godruoyi.com>
 *
 * This source file is subject to the MIT license that is bundled.
 */

namespace Godruoyi\Container;

use ArrayAccess;
use Closure;
use Exception;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionException;
use ReflectionFunction;
use ReflectionIntersectionType;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionUnionType;

class Container implements ArrayAccess, ContainerInterface
{
    /**
     * The Container instance.
     *
     * @var static
     */
    protected static Container $instance;

    /**
     * An array of objects that have been resolved in the container.
     *
     * @var array
     */
    protected array $resolved = [];

    /**
     * Bind array objects in the container.
     *
     * @var array
     */
    protected array $bindings = [];

    /**
     * An array for instance objects.
     *
     * @var array
     */
    protected array $instances = [];

    /**
     * Alias array.
     *
     * for example:
     *
     *  db => \Laravel\DB::class
     *
     * @var array
     */
    protected array $aliases = [];

    /**
     * An array for object extends array.
     *
     * @var array
     */
    protected array $extenders = [];

    /**
     * Rebound callback list.
     *
     * @var array
     */
    protected array $reboundCallbacks = [];

    /**
     * Has bound in this container for grieved abstract.
     *
     * @param  string  $abstract
     * @return bool
     */
    public function bound(string $abstract): bool
    {
        $abstract = $this->normalize($abstract);

        return isset($this->bindings[$abstract]) || isset($this->instances[$abstract]) || $this->isAlias($abstract);
    }

    /**
     * Set alias for abstract.
     *
     * @param  string  $abstract
     * @param  string  $alias
     * @return void
     *
     * @throws Exception
     */
    public function alias(string $abstract, string $alias): void
    {
        if ($alias === $abstract) {
            throw new Exception("[$abstract] is aliased to itself.");
        }

        $this->aliases[$alias] = $this->normalize($abstract);
    }

    /**
     *  {@inheritdoc}
     */
    public function has(string $id): bool
    {
        return $this->offsetExists($id);
    }

    /**
     *  {@inheritdoc}
     */
    public function get(string $id)
    {
        return $this[$id];
    }

    /**
     * Bind a class to container.
     *
     * @param  string|array  $abstract
     * @param  Closure|string|null  $concrete
     * @param  bool  $shared true set is single instance
     * @return void
     *
     * @throws Exception
     */
    public function bind($abstract, $concrete = null, bool $shared = false): void
    {
        $abstract = $this->normalize($abstract);

        $concrete = $this->normalize($concrete);

        if (is_array($abstract)) {
            [$abstract, $alias] = $this->extractAlias($abstract);

            $this->alias($abstract, $alias);
        }

        $this->dropStaleInstances($abstract);

        if (is_null($concrete)) {
            $concrete = $abstract;
        }

        if (! $concrete instanceof Closure) {
            $concrete = $this->getClosure($abstract, $concrete);
        }

        $this->bindings[$abstract] = compact('concrete', 'shared');

        if ($this->resolved($abstract)) {
            $this->rebound($abstract);
        }
    }

    /**
     * Register a binding if it hasn't already been registered.
     *
     * @param  string  $abstract
     * @param  Closure|string|null  $concrete
     * @param  bool  $shared
     * @return void
     *
     * @throws Exception
     */
    public function bindIf(string $abstract, $concrete = null, bool $shared = false): void
    {
        if (! $this->bound($abstract)) {
            $this->bind($abstract, $concrete, $shared);
        }
    }

    /**
     * Register a shared binding in the container.
     *
     * @param  string|array  $abstract
     * @param  Closure|string|null  $concrete
     * @return void
     *
     * @throws Exception
     */
    public function singleton($abstract, $concrete = null): void
    {
        $this->bind($abstract, $concrete, true);
    }

    /**
     * "Extend" an abstract type in the container.
     *
     * @param  string  $abstract
     * @param  Closure  $closure
     * @return void
     *
     * @throws InvalidArgumentException
     * @throws Exception
     */
    public function extend(string $abstract, Closure $closure): void
    {
        $abstract = $this->normalize($abstract);

        if (isset($this->instances[$abstract])) {
            $this->instances[$abstract] = $closure($this->instances[$abstract], $this);

            $this->rebound($abstract);
        } else {
            $this->extenders[$abstract][] = $closure;
        }
    }

    /**
     * Call the given Closure / class@method and inject its dependencies.
     *
     * @param  callable|string  $callback
     * @param  array  $parameters
     * @param  string|null  $defaultMethod
     * @return mixed
     *
     * @throws Exception
     */
    public function call($callback, array $parameters = [], string $defaultMethod = null): mixed
    {
        if ($this->isCallableWithAtSign($callback) || $defaultMethod) {
            return $this->callClass($callback, $parameters, $defaultMethod);
        }

        $dependencies = $this->getMethodDependencies($callback, $parameters);

        return call_user_func_array($callback, $dependencies);
    }

    /**
     * Register an existing instance as shared in the container.
     *
     * @param  string  $abstract
     * @param  mixed  $instance
     * @return void
     *
     * @throws Exception
     */
    public function instance(string $abstract, mixed $instance): void
    {
        $abstract = $this->normalize($abstract);

        if (is_array($abstract)) {
            [$abstract, $alias] = $this->extractAlias($abstract);

            $this->alias($abstract, $alias);
        }

        unset($this->aliases[$abstract]);

        $bound = $this->bound($abstract);

        $this->instances[$abstract] = $instance;

        if ($bound) {
            $this->rebound($abstract);
        }
    }

    /**
     * Make a instance.
     *
     * @param  string  $abstract
     * @param  array  $parameters
     * @return mixed
     *
     * @throws Exception
     */
    public function make(string $abstract, array $parameters = []): mixed
    {
        $abstract = $this->getAlias($this->normalize($abstract));

        if (isset($this->instances[$abstract]) && ! is_null($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        $concrete = $this->getConcrete($abstract);

        if ($this->isBuildable($concrete, $abstract)) {
            $object = $this->build($concrete, $parameters);
        } else {
            $object = $this->make($concrete, $parameters);
        }

        foreach ($this->getExtenders($abstract) as $extender) {
            $object = $extender($object, $this);
        }

        if ($this->isShared($abstract)) {
            $this->instances[$abstract] = $object;
        }

        $this->resolved[$abstract] = true;

        return $object;
    }

    /**
     * Build a instance for given class.
     *
     * @param  mixed  $concrete
     * @param  array  $parameters
     * @return mixed
     *
     * @throws ReflectionException
     * @throws Exception
     */
    public function build(mixed $concrete, array $parameters = []): mixed
    {
        if ($concrete instanceof Closure) {
            return $concrete($this, $parameters);
        }

        $reflector = new ReflectionClass($concrete);

        if (! $reflector->isInstantiable()) {
            throw new Exception("Target [$concrete] is not instantiable");
        }

        $constructor = $reflector->getConstructor();

        if (is_null($constructor)) {
            return new $concrete();
        }

        $dependencies = $constructor->getParameters();

        $parameters = $this->keyParametersByArgument(
            $dependencies,
            $parameters
        );

        $instances = $this->getDependencies(
            $dependencies,
            $parameters
        );

        return $reflector->newInstanceArgs($instances);
    }

    /**
     * Check abstract has resolved.
     *
     * @param  string  $abstract
     * @return bool
     */
    public function resolved(string $abstract): bool
    {
        $abstract = $this->normalize($abstract);

        if ($this->isAlias($abstract)) {
            $abstract = $this->getAlias($abstract);
        }

        return isset($this->resolved[$abstract]) || isset($this->instances[$abstract]);
    }

    /**
     * Determine if the given string is in Class@method syntax.
     *
     * @param  mixed  $callback
     * @return bool
     */
    protected function isCallableWithAtSign(mixed $callback): bool
    {
        return is_string($callback) && str_contains($callback, '@');
    }

    /**
     * Get all dependencies for a given method.
     *
     * @param  callable|string  $callback
     * @param  array  $parameters
     * @return array
     *
     * @throws ReflectionException
     * @throws Exception
     */
    protected function getMethodDependencies(mixed $callback, array $parameters = []): array
    {
        $dependencies = [];

        foreach ($this->getCallReflector($callback)->getParameters() as $parameter) {
            $this->addDependencyForCallParameter($parameter, $parameters, $dependencies);
        }

        return array_merge($dependencies, $parameters);
    }

    /**
     * Get the proper reflection instance for the given callback.
     *
     * @param  callable|string  $callback
     * @return ReflectionMethod|ReflectionFunction
     *
     * @throws ReflectionException
     */
    protected function getCallReflector(mixed $callback): ReflectionMethod|ReflectionFunction
    {
        if (is_string($callback) && str_contains($callback, '::')) {
            $callback = explode('::', $callback);
        }

        if (is_array($callback)) {
            return new ReflectionMethod($callback[0], $callback[1]);
        }

        return new ReflectionFunction($callback);
    }

    /**
     * Get the dependency for the given call parameter.
     *
     * @param  ReflectionParameter  $parameter
     * @param  array  $parameters
     * @param  array  $dependencies
     * @return void
     *
     * @throws Exception
     */
    protected function addDependencyForCallParameter(ReflectionParameter $parameter, array &$parameters, array &$dependencies): void
    {
        if (array_key_exists($parameter->name, $parameters)) {
            $dependencies[] = $parameters[$parameter->name];

            unset($parameters[$parameter->name]);
        } elseif ($this->getParameterClass($parameter)) {
            $dependencies[] = $this->make($this->getParameterClass($parameter)->getName());
        } elseif ($parameter->isDefaultValueAvailable()) {
            $dependencies[] = $parameter->getDefaultValue();
        }
    }

    /**
     * Compatible with PHP 5.3.
     *
     * @param  ReflectionParameter  $p
     * @return ReflectionIntersectionType|ReflectionNamedType|ReflectionUnionType|null
     */
    protected function getParameterClass(ReflectionParameter $p)
    {
        return $p->getType();
    }

    /**
     * Call a string reference to a class using Class@method syntax.
     *
     * @param  string  $target
     * @param  array  $parameters
     * @param  string|null  $defaultMethod
     * @return mixed
     *
     * @throws Exception
     */
    protected function callClass(string $target, array $parameters = [], string $defaultMethod = null): mixed
    {
        $segments = explode('@', $target);

        // If the listener has an @ sign, we will assume it is being used to delimit
        // the class name from the handle method name. This allows for handlers
        // to run multiple handler methods in a single class for convenience.
        $method = count($segments) == 2 ? $segments[1] : $defaultMethod;

        if (is_null($method)) {
            throw new Exception('Method not provided.');
        }

        return $this->call([$this->make($segments[0]), $method], $parameters);
    }

    /**
     * Normalized service name.
     *
     * @param  mixed  $service
     * @return mixed
     */
    protected function normalize(mixed $service): mixed
    {
        return is_string($service) ? ltrim($service, '\\') : $service;
    }

    /**
     * Check name has a alias in container.
     *
     * @param  string  $name
     * @return bool
     */
    public function isAlias(string $name): bool
    {
        return isset($this->aliases[$this->normalize($name)]);
    }

    /**
     * Get alias.
     *
     * @param  array  $alias
     * @return array
     */
    protected function extractAlias(array $alias): array
    {
        return [key($alias), current($alias)];
    }

    /**
     * Delete instance in container.
     *
     * @param  string  $abstract
     * @return void
     */
    protected function dropStaleInstances(string $abstract): void
    {
        unset($this->instances[$abstract], $this->aliases[$abstract]);
    }

    /**
     * @param  string  $abstract
     * @param  string  $concrete
     * @return Closure
     */
    protected function getClosure(string $abstract, string $concrete): Closure
    {
        return function ($container, array $parameters = []) use ($abstract, $concrete) {
            $method = ($abstract == $concrete) ? 'build' : 'make';

            return $container->$method($concrete, $parameters);
        };
    }

    /**
     * Get alias for given abstract if available.
     *
     * @param  string  $abstract
     * @return string
     */
    public function getAlias(string $abstract): string
    {
        if (! isset($this->aliases[$abstract])) {
            return $abstract;
        }

        return $this->getAlias($this->aliases[$abstract]);
    }

    /**
     * Rebind abstract to container.
     *
     * @param  string  $abstract
     * @return void
     *
     * @throws Exception
     */
    protected function rebound(string $abstract): void
    {
        $instance = $this->make($abstract);

        foreach ($this->getReboundCallbacks($abstract) as $callback) {
            call_user_func($callback, $this, $instance);
        }
    }

    /**
     * Get abstract extender.
     *
     * @param  string  $abstract
     * @return array
     */
    protected function getExtenders(string $abstract): array
    {
        if (isset($this->extenders[$abstract])) {
            return $this->extenders[$abstract];
        }

        return [];
    }

    /**
     * Check abstract is shared in container.
     *
     * @param  string  $abstract
     * @return bool
     */
    protected function isShared(string $abstract): bool
    {
        $abstract = $this->normalize($abstract);

        if (isset($this->instances[$abstract])) {
            return true;
        }

        if (! isset($this->bindings[$abstract]['shared'])) {
            return false;
        }

        return $this->bindings[$abstract]['shared'] === true;
    }

    /**
     * Get abstract type in container.
     *
     * @param  string  $abstract
     * @return mixed
     */
    public function getConcrete(string $abstract): mixed
    {
        if (! isset($this->bindings[$abstract])) {
            return $abstract;
        }

        return $this->bindings[$abstract]['concrete'];
    }

    /**
     * Check abstract and concrete has buildable.
     *
     * @param  mixed  $concrete
     * @param  string  $abstract
     * @return bool
     */
    public function isBuildable(mixed $concrete, string $abstract): bool
    {
        return $concrete === $abstract || $concrete instanceof Closure;
    }

    /**
     * Get Rebound Callbacks.
     *
     * @param  string  $abstract
     * @return array
     */
    public function getReboundCallbacks(string $abstract): array
    {
        if (isset($this->reboundCallbacks[$abstract])) {
            return $this->reboundCallbacks[$abstract];
        }

        return [];
    }

    /**
     * $app->build('Some\Class', [params..]).
     *
     * @param  array  $dependencies
     * @param  array  $parameters
     * @return array
     */
    public function keyParametersByArgument(array $dependencies, array $parameters): array
    {
        foreach ($parameters as $key => $value) {
            if (is_numeric($key)) {
                unset($parameters[$key]);

                $parameters[$dependencies[$key]->name] = $value;
            }
        }

        return $parameters;
    }

    /**
     * Get dependencies.
     *
     * @param  array  $dependencies
     * @param  array  $parameters
     * @return array
     *
     * @throws Exception
     */
    protected function getDependencies(array $dependencies, array $parameters): array
    {
        $dependenciesArr = [];

        foreach ($dependencies as $parameter) {
            $dependency = $this->getParameterClass($parameter);

            if (array_key_exists($parameter->name, $parameters)) {
                $dependenciesArr[] = $parameters[$parameter->name];
            } elseif (is_null($dependency)) {
                $dependenciesArr[] = $this->resolveNonClass($parameter);
            } else {
                $dependenciesArr[] = $this->resolveClass($parameter);
            }
        }

        return $dependenciesArr;
    }

    /**
     * Resolve no class construct.
     *
     * @param  ReflectionParameter  $parameter
     * @return mixed
     *
     * @throws Exception
     */
    protected function resolveNonClass(ReflectionParameter $parameter): mixed
    {
        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        $message = "Unresolvable dependency resolving [$parameter] in class {$parameter->getDeclaringClass()->getName()}";

        throw new Exception($message);
    }

    /**
     * Resolve Reflection Parameter.
     *
     * @param  ReflectionParameter  $parameter
     * @return mixed
     *
     * @throws ReflectionException
     */
    protected function resolveClass(ReflectionParameter $parameter): mixed
    {
        try {
            return $this->make($this->getParameterClass($parameter)->getName());
        } catch (Exception $e) {
            if ($parameter->isOptional()) {
                return $parameter->getDefaultValue();
            }

            throw $e;
        }
    }

    /**
     * Determine if a given offset exists.
     *
     * @param  string  $offset
     * @return bool
     */
    public function offsetExists(mixed $offset): bool
    {
        return $this->bound($offset);
    }

    /**
     * Get the value at a given offset.
     *
     * @param  string  $offset
     * @return mixed
     *
     * @throws Exception
     */
    public function offsetGet($offset): mixed
    {
        return $this->make($offset);
    }

    /**
     * Set the value at a given offset.
     *
     * @param  string  $offset
     * @param  mixed  $value
     * @return void
     *
     * @throws Exception
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        // If the value is not a Closure, we will make it one. This simply gives
        // more "drop-in" replacement functionality for the Pimple which this
        // container's simplest functions are base modeled and built after.
        if (! $value instanceof Closure) {
            $value = function () use ($value) {
                return $value;
            };
        }

        $this->bind($offset, $value);
    }

    /**
     * Unset the value at a given offset.
     *
     * @param  string  $offset
     * @return void
     */
    public function offsetUnset(mixed $offset): void
    {
        $key = $this->normalize($offset);

        unset($this->bindings[$key], $this->instances[$key], $this->resolved[$key]);
    }

    /**
     * Dynamically access container services.
     *
     * @param  string  $key
     * @return mixed
     */
    public function __get(string $key)
    {
        return $this[$key];
    }

    /**
     * Dynamically set container services.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return void
     */
    public function __set(string $key, mixed $value)
    {
        $this[$key] = $value;
    }
}
