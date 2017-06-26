<?php

namespace Godruoyi\Container;

use Closure;
use ArrayAccess;
use Exception;
use ReflectionClass;
use ReflectionMethod;
use ReflectionFunction;
use ReflectionParameter;

/**
 * 参考自 laravel - Illuminate\Container\Container
 *
 * 简化部分逻辑， 添加中文注释
 * 
 */
class Container implements ContainerInterface, ArrayAccess
{
	/**
	 * 容器实列对象
	 * 
	 * @var static
	 */
	protected static $instance;

	/**
	 * 已经解决的对象集合
	 * 
	 * @var array
	 */
	protected $resoleved = array();

	/**
	 * 绑定在容器上的对象集合
	 * 
	 * @var array
	 */
	protected $bindings = array();

	/**
	 * 绑定到容器的实列对象集合
	 * 
	 * @var array
	 */
	protected $instances = array();

	/**
	 * 注册一个 笔名 => 对应的类 到容器中
	 * 
	 * @var array
	 */
	protected $aliases = array();

	/**
	 * 扩展容器中已绑定或注册的对象， closures
	 * 
	 * @var array
	 */
	protected $extenders = array();

	/**
     * 重新绑定对象到容器时的回调函数集合，
     *
     * @var array
     */
    protected $reboundCallbacks = array();

	/**
     * 判断给定的抽象类型是否已经绑定到容器上
     *
     * @param  string  $abstract
     * @return bool
     */
    public function bound($abstract)
    {
    	$abstract = $this->normalize($abstract);

        return isset($this->bindings[$abstract]) || isset($this->instances[$abstract]) || $this->isAlias($abstract);
    }

    /**
     * 设置别名到容器笔名数组中
     *
     * @param  string  $abstract
     * @param  string  $alias
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
     * 绑定一个抽象类到容器中
     *
     * @param  string|array  $abstract 抽象类
     * @param  \Closure|string|null  $concrete 具体类
     * @param  bool  $shared 绑定到容器的对象是否共享， 即后续取出的对象是否为同一对象 
     *                       $app['someClass'] === $app['someClass'] //true
     *                       shared为true时表示每次向容器取出同一对象
     * @return void
     */
    public function bind($abstract, $concrete = null, $shared = false)
    {
    	$abstract = $this->normalize($abstract);

    	$concrete = $this->normalize($concrete);

    	//当给定的抽象类型是一个数组时([abstract => alias])， 则为该抽象类设置别名，
    	if (is_array($abstract)) {
    		list($abstract, $alias) = $this->extractAlias($abstract);

    		//注册抽象类 => 别名
    		$this->alias($abstract, $alias);
    	}

    	//绑定（abstract => concrete）到容器时， 先删除容器中已存在的该抽象绑定
    	$this->dropStaleInstances($abstract);

    	//当给定的具体类型为空时 ($app->bind('some\Class'))，我们将简单的设置 抽象类型 = 具体类型， 
    	//后续构造该抽象类型时，设置构造后的实列对象为 共享 状态
    	if (is_null($concrete)) {
    		$concrete = $abstract;
    	}

    	// 当给定的具体类型不是一个匿名函数时， 意味着该具体类型只是一个 类名，
    	// 把该具体类型注册到容器中时， 用匿名函数包裹起来， 当其需要实列化时， 
    	// 从容器上下文中获取更多的依赖来实列化他
    	if (! $concrete instanceof Closure) {
            $concrete = $this->getClosure($abstract, $concrete);
        }

        //绑定 （抽象类型/别名 => 闭包） 到容器上
        $this->bindings[$abstract] = compact('concrete', 'shared');

        //若绑定到容器的对象先前已注册过， 则重新绑定
        if ($this->resolved($abstract)) {
        	$this->rebound($abstract);
        }
    }

    /**
     * Register a binding if it hasn't already been registered.
     *
     * @param  string  $abstract
     * @param  \Closure|string|null  $concrete
     * @param  bool  $shared
     * @return void
     */
    public function bindIf($abstract, $concrete = null, $shared = false)
    {
    	if (! $this->bound($abstract)) {
    		$this->bind($abstract, $concrete, $shared);
    	}
    }

    /**
     * Register a shared binding in the container.
     *
     * @param  string|array  $abstract
     * @param  \Closure|string|null  $concrete
     * @return void
     */
    public function singleton($abstract, $concrete = null)
    {
    	$this->bind($abstract, $concrete, true);
    }

    /**
     * "Extend" an abstract type in the container.
     *
     * @param  string    $abstract
     * @param  \Closure  $closure
     * @return void
     *
     * @throws \InvalidArgumentException
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
     * @param  callable|string  $callback
     * @param  array  $parameters
     * @param  string|null  $defaultMethod
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
     * Determine if the given string is in Class@method syntax.
     *
     * @param  mixed  $callback
     * @return bool
     */
    protected function isCallableWithAtSign($callback)
    {
        return is_string($callback) && strpos($callback, '@') !== false;
    }

    /**
     * Get all dependencies for a given method.
     *
     * @param  callable|string  $callback
     * @param  array  $parameters
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
     * @param  callable|string  $callback
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
     * @param  \ReflectionParameter  $parameter
     * @param  array  $parameters
     * @param  array  $dependencies
     * @return mixed
     */
    protected function addDependencyForCallParameter(ReflectionParameter $parameter, array &$parameters, &$dependencies)
    {
        if (array_key_exists($parameter->name, $parameters)) {
            $dependencies[] = $parameters[$parameter->name];

            unset($parameters[$parameter->name]);
        } elseif ($parameter->getClass()) {
            $dependencies[] = $this->make($parameter->getClass()->name);
        } elseif ($parameter->isDefaultValueAvailable()) {
            $dependencies[] = $parameter->getDefaultValue();
        }
    }

    /**
     * Call a string reference to a class using Class@method syntax.
     *
     * @param  string  $target
     * @param  array  $parameters
     * @param  string|null  $defaultMethod
     * @return mixed
     *
     * @throws \Exception
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

        return $this->call([$this->make($segments[0]), $method], $parameters);
    }

    /**
     * Register an existing instance as shared in the container.
     *
     * @param  string  $abstract
     * @param  mixed   $instance
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
     * 实例化对象， 通过给定的抽象类型及参数
     *
     * @param  string  $abstract
     * @param  array   $parameters
     * @return mixed
     */
    public function make($abstract, array $parameters = array())
    {
    	$abstract = $this->getAlias($this->normalize($abstract));

    	//反复make同一抽象时， 返回同一个对象
    	if (isset($this->instances[$abstract]) && !is_null($this->instances[$abstract])) {
    		return $this->instances[$abstract];
    	}

    	//获取抽象类对应的具体对象
    	$concrete = $this->getConcrete($abstract);

    	//当前具体类型是可以build时，
    	if ($this->isBuildable($concrete, $abstract)) {
    		$object = $this->build($concrete, $parameters);
    	} else {
    		$object = $this->make($concrete, $parameters);
    	}

        //若容器中有该具体类的扩展回调
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
     * 构建一个具体类型实列， 
     * 
     * @param  mixed $concrete  
     * @param  array  $parameters
     * @return mixed
     */
    public function build($concrete, array $parameters = array())
    {
    	// 当具体类型是一个Closure类型时， 直接返回函数的返回值
    	if ($concrete instanceof Closure) { 
    		return $concrete($this, $parameters);
    	}

    	$reflector = new ReflectionClass($concrete);

    	//具体类型是不可实例化的 - 直接报错
    	if (! $reflector->isInstantiable()) {
    		throw new \Exception("Target [$concrete] is not instantiable");
    	}

    	//获取类的构造函数 返回一个 ReflectionMethod 对象，反射了类的构造函数，
    	//或者当类不存在构造函数时返回 NULL。
    	$constructor = $reflector->getConstructor();

    	// 不存在构造函数， 直接实例化
    	if (is_null($constructor)) {
    		return new $concrete;
    	}

    	//构造函数参数 返回 ReflectionParameter对象构成的array 
    	$dependencies = $constructor->getParameters();

    	// 构建构造函数参数通过传入的参数，
    	$parameters = $this->keyParametersByArgument(
            $dependencies, $parameters
        );

        $instances = $this->getDependencies(
            $dependencies, $parameters
        );

        return $reflector->newInstanceArgs($instances);
    }

    /**
     * 判断给定的抽象类型/别名 是否在容器中已是解决的
     *
     * @param  string $abstract
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
     * Register a new resolving callback.
     *
     * @param  string    $abstract
     * @param  \Closure|null  $callback
     * @return void
     */
    public function resolving($abstract, Closure $callback = null)
    {

    }

    /**
     * Register a new after resolving callback.
     *
     * @param  string    $abstract
     * @param  \Closure|null  $callback
     * @return void
     */
    public function afterResolving($abstract, Closure $callback = null)
    {

    }

    /**
     * 规范化给定的类名 - 通过删除前面的反斜杠
     *
     * @param  mixed  $service
     * @return mixed
     */
    protected function normalize($service)
    {
        return is_string($service) ? ltrim($service, '\\') : $service;
    }

    /**
     * 判断给定的抽象类是否在 容器-笔名数组中注册
     *
     * @param  string  $name
     * @return bool
     */
    public function isAlias($name)
    {
        return isset($this->aliases[$this->normalize($name)]);
    }

    /**
     * 提取别名
     * 
     * @param  array  $alias
     * @return array
     */
    protected function extractAlias(array $alias)
    {
    	return array(key($alias), current($alias));
    }

    /**
     * 删除容器中陈旧的实列对象 - 根据给定的抽象类型, (只删除实列对象)
     * 
     * @param  string $abstract
     * @return void
     */
    protected function dropStaleInstances($abstract)
    {
    	unset($this->instances[$abstract], $this->aliases[$abstract]);
    }

    /**
     * 获取一个方法闭包， 根据指定的抽象类型及具体类型
     * 
     * @param  string $abstract
     * @param  string $concrete
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
     * 根据给定的抽象类获取别名
     * 
     * @param  string $abstract
     * @return string          
     */
    public function getAlias($abstract)
    {
    	if (! isset($this->aliases[$abstract])) {
            return $abstract;
        }

        return $this->getAlias($this->aliases[$abstract]);
    }

    /**
     * 重新绑定给定的抽象类到容器中， 
     * 
     * @param string $abstract
     * @return void
     */
    protected function rebound($abstract)
    {
    	//通过给定的抽象类型 - 实例化具体对象
    	$instance = $this->make($abstract);

    	//当我们在重新绑定实列到容器中时， 可也为每个从新绑定定义回调函数，
    	//方便对象在重新绑定回容器时， 对其进行炒作
    	foreach ($this->getReboundCallbacks($abstract) as $callback) {
    		call_user_func($callback, $this, $instance);
    	}
    }

    /**
     * 通过给定的抽象类型获取扩展回调
     * 
     * @param  string $abstract
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
     * 判断给定的抽象类， 当其实列化对应的具体对象时， 该对象是否是共享的
     * 
     * @param  string  $abstract
     * @return boolean          
     */
    protected function isShared($abstract)
    {
    	$abstract = $this->normalize($abstract);

    	//存在实列集合中， 肯定是共享的
    	if (isset($this->instances[$abstract])) {
    		return true;
    	}

    	if (! isset($this->bindings[$abstract]['shared'])) {
    		return false;
    	}

    	return $this->bindings[$abstract]['shared'] === true;
    }

    /**
     * 根据抽象类型获取具体类型
     * 
     * @param  string $abstract
     * @return mixed
     */
    public function getConcrete($abstract)
    {
    	// 当给定的抽象类型没有在容器中绑定时， 直接返回抽象类型
    	// 如当$app->make('SomeClass\Class')时， 直接返回具体类型为 SomeClass\Class
    	if (! isset($this->bindings[$abstract])) {
    		return $abstract;
    	}

    	return $this->bindings[$abstract]['concrete'];
    }

    /**
     * 判断给定的抽象类型及具体类似是否是可构建的(build)
     * 
     * @param  mixed $concrete
     * @param  string $abstract
     * @return boolean          
     */
    public function isBuildable($concrete, $abstract)
    {
    	return $concrete === $abstract || $concrete instanceof Closure;
    }

    /**
     * 获取重新绑定时的回调数组
     * 
     * @param  string $abstract 
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
     * 构造参数 通过传入的参数
     *
     * $app->build('Some\Class', [params..])
     * 
     * @param  array $dependencies 具体类型的构造函数参数数组
     * @param  array $parameters   传入的参数数组
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
     * 获取构造函数的参数，当传入的参数没有指定时
     * 
     * @param  array  $dependencies 构造函数依赖的参数
     * @param  array  $parameters    传入的参数
     * @return 
     */
    protected function getDependencies(array $dependencies, array $parameters)
    {
    	$dependenciesArr = array();

    	foreach ($dependencies as $parameter) {
    		$dependency = $parameter->getClass();

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
     * 解决没有类的构造函数
     * 
     * @param  ReflectionParameter $parameter
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
     * 解决构造函数含有其他类参数的情况
     * 
     * @param  ReflectionParameter $parameter
     * @return 
     */
    protected function resolveClass(ReflectionParameter $parameter)
    {
    	try {
            return $this->make($parameter->getClass()->name);
        }

        catch (\Exception $e) {
            if ($parameter->isOptional()) {
                return $parameter->getDefaultValue();
            }

            throw $e;
        }
    }

    /**
     * Determine if a given offset exists.
     *
     * @param  string  $key
     * @return bool
     */
    public function offsetExists($key)
    {
        return $this->bound($key);
    }

    /**
     * Get the value at a given offset.
     *
     * @param  string  $key
     * @return mixed
     */
    public function offsetGet($key)
    {
        return $this->make($key);
    }

    /**
     * Set the value at a given offset.
     *
     * @param  string  $key
     * @param  mixed   $value
     * @return void
     */
    public function offsetSet($key, $value)
    {
        // If the value is not a Closure, we will make it one. This simply gives
        // more "drop-in" replacement functionality for the Pimple which this
        // container's simplest functions are base modeled and built after.
        if (! $value instanceof Closure) {
            $value = function () use ($value) {
                return $value;
            };
        }

        $this->bind($key, $value);
    }

    /**
     * Unset the value at a given offset.
     *
     * @param  string  $key
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
     * @param  string  $key
     * @return mixed
     */
    public function __get($key)
    {
        return $this[$key];
    }

    /**
     * Dynamically set container services.
     *
     * @param  string  $key
     * @param  mixed   $value
     * @return void
     */
    public function __set($key, $value)
    {
        $this[$key] = $value;
    }
}