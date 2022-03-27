<?php

namespace Godruoyi\Container;

use ArrayAccess;
use Closure;
use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionParameter;

class Container implements ContainerInterface, ArrayAccess
{
    /**
     * The Container instance.
     *
     * @var static
     */
    protected static $instance;

    /**
     * An array of objects that have been resolved in the container.
     *
     * @var array
     */
    protected $resoleved = array();

    /**
     * Bind array objects in the container.
     *
     * @var array
     */
    protected $bindings = array();

    /**
     * An array for instance objects.
     *
     * @var array
     */
    protected $instances = array();

    /**
     * Alias array.
     *
     * forexample:
     *
     *  db => \Laravel\DB::class
     *
     * @var array
     */
    protected $aliases = array();

    /**
     * An array for object extends array.
     *
     * @var array
     */
    protected $extenders = array();

    /**
     * Rebound callbank list.
     *
     * @var array
     */
    protected $reboundCallbacks = array();

    /**
     * Has bound in this container for gieved abstract.
     *
     * @param string $abstract
     *
     * @return bool
     */
    public function bound($abstract)
    {
        $abstract = $this->normalize($abstract);

        return isset($this->bindings[$abstract]) || isset($this->instances[$abstract]) || $this->isAlias($abstract);
    }

    /**
     * Set alias for abstract.
     *
     * @param string $abstract
     * @param string $alias
     *
     * @return void
     */
    public function alias($abstract, $alias)
    {
        if ($alias === $abstract) {
            throw new \Exception("[{$abstract}] is aliased to itself.");
        }

        $this->aliases[$alias] = $this->normalize($abstract);
    }

    /**
     *  {@inheritdoc}
     */
    public function has($id)
    {
        return $this->offsetExists($id);
    }

    /**
     *  {@inheritdoc}
     */
    public function get($id)
    {
        return $this[$id];
    }

    /**
     * Bind a class to container.
     *
     * @param string|array         $abstract
     * @param \Closure|string|null $concrete
     * @param bool                 $shared   true set is single instance
     *
     * @return void
     */
    public function bind($abstract, $concrete = null, $shared = false)
    {
        $abstract = $this->normalize($abstract);

        $concrete = $this->normalize($concrete);

        if (is_array($abstract)) {
            list($abstract, $alias) = $this->extractAlias($abstract);

            $this->alias($abstract, $alias);
        }

        $this->dropStaleInstances($abstract);

        if (is_null($concrete)) {
            $concrete = $abstract;
        }

        if (!$concrete instanceof Closure) {
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
     * @param string               $abstract
     * @param \Closure|string|null $concrete
     * @param bool                 $shared
     *
     * @return void
     */
    public function bindIf($abstract, $concrete = null, $shared = false)
    {
        if (!$this->bound($abstract)) {
            $this->bind($abstract, $concrete, $shared);
        }
    }

    /**
     * Register a shared binding in the container.
     *
     * @param string|array         $abstract
     * @param \Closure|string|null $concrete
     *
     * @return void
     */
    public function singleton($abstract, $concrete = null)
    {
        $this->bind($abstract, $concrete, true);
    }

    /**
     * "Extend" an abstract type in the container.
     *
     * @param string   $abstract
     * @param \Closure $closure
     *
     * @throws \InvalidArgumentException
     *
     * @return void
     */
    public function extend($abstract, Closure $closure)
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
     * @param callable|string $callback
     * @param array           $parameters
     * @param string|null     $defaultMethod
     *
     * @return mixed
     */
    public function call($callback, array $parameters = array(), $defaultMethod = null)
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
     * @param string $abstract
     * @param mixed  $instance
     *
     * @return void
     */
    public function instance($abstract, $instance)
    {
        $abstract = $this->normalize($abstract);

        if (is_array($abstract)) {
            list($abstract, $alias) = $this->extractAlias($abstract);

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
     * @param string $abstract
     * @param array  $parameters
     *
     * @return mixed
     */
    public function make($abstract, array $parameters = array())
    {
        $abstract = $this->getAlias($this->normalize($abstract));

        if (isset($this->instances[$abstract]) && !is_null($this->instances[$abstract])) {
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

        $this->resoleved[$abstract] = true;

        return $object;
    }

    /**
     * Build a instance for gieved concrete.
     *
     * @param mixed $concrete
     * @param array $parameters
     *
     * @return mixed
     */
    public function build($concrete, array $parameters = array())
    {
        if ($concrete instanceof Closure) {
            return $concrete($this, $parameters);
        }

        $reflector = new ReflectionClass($concrete);

        if (!$reflector->isInstantiable()) {
            throw new \Exception("Target [$concrete] is not instantiable");
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
     * @param string $abstract
     *
     * @return bool
     */
    public function resolved($abstract)
    {
        $abstract = $this->normalize($abstract);

        if ($this->isAlias($abstract)) {
            $abstract = $this->getAlias($abstract);
        }

        return isset($this->resoleved[$abstract]) || isset($this->instances[$abstract]);
    }

    /**
     * Determine if the given string is in Class@method syntax.
     *
     * @param mixed $callback
     *
     * @return bool
     */
    protected function isCallableWithAtSign($callback)
    {
        return is_string($callback) && strpos($callback, '@') !== false;
    }

    /**
     * Get all dependencies for a given method.
     *
     * @param callable|string $callback
     * @param array           $parameters
     *
     * @return array
     */
    protected function getMethodDependencies($callback, array $parameters = array())
    {
        $dependencies = array();

        foreach ($this->getCallReflector($callback)->getParameters() as $parameter) {
            $this->addDependencyForCallParameter($parameter, $parameters, $dependencies);
        }

        return array_merge($dependencies, $parameters);
    }

    /**
     * Get the proper reflection instance for the given callback.
     *
     * @param callable|string $callback
     *
     * @return \ReflectionFunctionAbstract
     */
    protected function getCallReflector($callback)
    {
        if (is_string($callback) && strpos($callback, '::') !== false) {
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
     * @param \ReflectionParameter $parameter
     * @param array                $parameters
     * @param array                $dependencies
     *
     * @return mixed
     */
    protected function addDependencyForCallParameter(ReflectionParameter $parameter, array &$parameters, &$dependencies)
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
     * @param \ReflectionParameter $p
     *
     * @return mixed
     */
    protected function getParameterClass(ReflectionParameter $p)
    {
        if (version_compare(PHP_VERSION, '8.0.0') >= 0) {
            return $p->getType();
        }

        return $p->getClass();
    }

    /**
     * Call a string reference to a class using Class@method syntax.
     *
     * @param string      $target
     * @param array       $parameters
     * @param string|null $defaultMethod
     *
     * @throws \Exception
     *
     * @return mixed
     */
    protected function callClass($target, array $parameters = array(), $defaultMethod = null)
    {
        $segments = explode('@', $target);

        // If the listener has an @ sign, we will assume it is being used to delimit
        // the class name from the handle method name. This allows for handlers
        // to run multiple handler methods in a single class for convenience.
        $method = count($segments) == 2 ? $segments[1] : $defaultMethod;

        if (is_null($method)) {
            throw new \Exception('Method not provided.');
        }

        return $this->call(array($this->make($segments[0]), $method), $parameters);
    }

    /**
     * Normalized service name.
     *
     * @param mixed $service
     *
     * @return mixed
     */
    protected function normalize($service)
    {
        return is_string($service) ? ltrim($service, '\\') : $service;
    }

    /**
     * Check name has a alias in container.
     *
     * @param string $name
     *
     * @return bool
     */
    public function isAlias($name)
    {
        return isset($this->aliases[$this->normalize($name)]);
    }

    /**
     * Get alias.
     *
     * @param array $alias
     *
     * @return array
     */
    protected function extractAlias(array $alias)
    {
        return array(key($alias), current($alias));
    }

    /**
     * Delete instance in container.
     *
     * @param string $abstract
     *
     * @return void
     */
    protected function dropStaleInstances($abstract)
    {
        unset($this->instances[$abstract], $this->aliases[$abstract]);
    }

    /**
     * @param string $abstract
     * @param string $concrete
     *
     * @return \Closure
     */
    protected function getClosure($abstract, $concrete)
    {
        return function ($container, array $parameters = array()) use ($abstract, $concrete) {
            $method = ($abstract == $concrete) ? 'build' : 'make';

            return $container->$method($concrete, $parameters);
        };
    }

    /**
     * Get alias for gieved abstract.
     *
     * @param string $abstract
     *
     * @return string
     */
    public function getAlias($abstract)
    {
        if (!isset($this->aliases[$abstract])) {
            return $abstract;
        }

        return $this->getAlias($this->aliases[$abstract]);
    }

    /**
     * Rebing abstract to container.
     *
     * @param string $abstract
     *
     * @return void
     */
    protected function rebound($abstract)
    {
        $instance = $this->make($abstract);

        foreach ($this->getReboundCallbacks($abstract) as $callback) {
            call_user_func($callback, $this, $instance);
        }
    }

    /**
     * Get gieved abstract ectedns.
     *
     * @param string $abstract
     *
     * @return array
     */
    protected function getExtenders($abstract)
    {
        if (isset($this->extenders[$abstract])) {
            return $this->extenders[$abstract];
        }

        return array();
    }

    /**
     * Check absteact is shared in container.
     *
     * @param string $abstract
     *
     * @return bool
     */
    protected function isShared($abstract)
    {
        $abstract = $this->normalize($abstract);

        if (isset($this->instances[$abstract])) {
            return true;
        }

        if (!isset($this->bindings[$abstract]['shared'])) {
            return false;
        }

        return $this->bindings[$abstract]['shared'] === true;
    }

    /**
     * Get abstract type in container.
     *
     * @param string $abstract
     *
     * @return mixed
     */
    public function getConcrete($abstract)
    {
        if (!isset($this->bindings[$abstract])) {
            return $abstract;
        }

        return $this->bindings[$abstract]['concrete'];
    }

    /**
     * Check abstract and concrete has buildable.
     *
     * @param mixed  $concrete
     * @param string $abstract
     *
     * @return bool
     */
    public function isBuildable($concrete, $abstract)
    {
        return $concrete === $abstract || $concrete instanceof Closure;
    }

    /**
     * Get Rebound Callbacks.
     *
     * @param string $abstract
     *
     * @return array
     */
    public function getReboundCallbacks($abstract)
    {
        if (isset($this->reboundCallbacks[$abstract])) {
            return $this->reboundCallbacks[$abstract];
        }

        return array();
    }

    /**
     * $app->build('Some\Class', [params..]).
     *
     * @param array $dependencies
     * @param array $parameters
     *
     * @return array
     */
    public function keyParametersByArgument(array $dependencies, array $parameters)
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
     * @param array $dependencies
     * @param array $parameters
     *
     * @return array
     */
    protected function getDependencies(array $dependencies, array $parameters)
    {
        $dependenciesArr = array();

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
     * @param ReflectionParameter $parameter
     *
     * @return
     */
    protected function resolveNonClass(ReflectionParameter $parameter)
    {
        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        $message = "Unresolvable dependency resolving [$parameter] in class {$parameter->getDeclaringClass()->getName()}";

        throw new \Exception($message);
    }

    /**
     * Resolve Reflection Parameter.
     *
     * @param ReflectionParameter $parameter
     *
     * @return
     */
    protected function resolveClass(ReflectionParameter $parameter)
    {
        try {
            return $this->make($this->getParameterClass($parameter)->getName());
        } catch (\Exception $e) {
            if ($parameter->isOptional()) {
                return $parameter->getDefaultValue();
            }

            throw $e;
        }
    }

    /**
     * Determine if a given offset exists.
     *
     * @param string $key
     *
     * @return bool
     */
    public function offsetExists($key)
    {
        return $this->bound($key);
    }

    /**
     * Get the value at a given offset.
     *
     * @param string $key
     *
     * @return mixed
     */
    public function offsetGet($key)
    {
        return $this->make($key);
    }

    /**
     * Set the value at a given offset.
     *
     * @param string $key
     * @param mixed  $value
     *
     * @return void
     */
    public function offsetSet($key, $value)
    {
        // If the value is not a Closure, we will make it one. This simply gives
        // more "drop-in" replacement functionality for the Pimple which this
        // container's simplest functions are base modeled and built after.
        if (!$value instanceof Closure) {
            $value = function () use ($value) {
                return $value;
            };
        }

        $this->bind($key, $value);
    }

    /**
     * Unset the value at a given offset.
     *
     * @param string $key
     *
     * @return void
     */
    public function offsetUnset($key)
    {
        $key = $this->normalize($key);

        unset($this->bindings[$key], $this->instances[$key], $this->resoleved[$key]);
    }

    /**
     * Dynamically access container services.
     *
     * @param string $key
     *
     * @return mixed
     */
    public function __get($key)
    {
        return $this[$key];
    }

    /**
     * Dynamically set container services.
     *
     * @param string $key
     * @param mixed  $value
     *
     * @return void
     */
    public function __set($key, $value)
    {
        $this[$key] = $value;
    }
}
