<?php

/**
 * @description Aspire's Inversion-of-Control dependency injection container, based on Dice
 *
 * @author      Tom Butler tom@r.je
 * @author      Garrett Whitehorn http://garrettw.net/
 * @license     http://www.opensource.org/licenses/bsd-license.php  BSD License
 */

namespace Aspire\DIC;

class Container implements \Psr\Container\ContainerInterface
{
    private $config = null;
    private $closures = [];
    private $instances = [];

    public function __construct(Config $config = null)
    {
        $this->config = $config;
    }

    public function &config()
    {
        return $this->config;
    }

    public function has($id)
    {
        return (class_exists($id)
            || ($this->hasConfig()
                && $this->config->getRule($id) != $this->config->getRule('*'))
        );
    }

    public function hasConfig()
    {
        return isset($this->config);
    }

    /**
     * Returns a fully constructed object based on $id, whether one exists already or not
     *
     * @param string $id
     * @return mixed
     */
    public function get($id, array $args = [])
    {
        if (!$this->has($id)) {
            throw new Exception\NotFoundException('Could not instantiate ' . $id);
        }

        if (empty($args) && !empty($this->instances[$id])) {
            // we've already created a shared instance so return it to save the closure call.
            return $this->instances[$id];
        }

        return $this->make($id, $args);
    }

    /**
     * Constructs and returns an object based on $id using $args and $share as constructor arguments
     *
     * @param string $id The name of the class to instantiate
     * @param array $args An array with any additional arguments to be passed into the constructor
     * @param array $share Whether the same class instance should be passed around each time
     * @return object A fully constructed object based on the specified input arguments
     */
    public function make($id, array $args = [], array $share = [])
    {
        // we either need a new instance or just don't have one stored
        // but if we have the closure stored that creates it, call that
        if (!empty($this->closures[$id])) {
            return $this->closures[$id]($args, $share);
        }

        $rule = ($this->hasConfig()) ? $this->config->getRule($id) : [];
        $class = new \ReflectionClass(isset($rule['instanceOf']) ? $rule['instanceOf'] : $id);

        $closure = $this->prepare($id, $rule, $class);

        if (isset($rule['shareInstances'])) {
            $closure = function(array $args, array $share) use ($closure, $rule) {
                return $closure($args, array_merge($args, $share, array_map([$this, 'get'], $rule['shareInstances'])));
            };
        }

        // When $rule['call'] is set, wrap the closure in another closure which calls the required methods after constructing the object.
        // By putting this in a closure, the loop is never executed unless call is actually set.
        if (isset($rule['call'])) {
            $closure = function(array $args, array $share) use ($closure, $class, $rule) {
                // Construct the object using the original closure
                $object = $closure($args, $share);
                foreach ($rule['call'] as $call) {
                    // Generate the method arguments using prepareParams() and call the returned closure
                    // (in php7 it will be ()() rather than __invoke)
                    $shareRule = ['shareInstances' => isset($rule['shareInstances']) ? $rule['shareInstances'] : []];
                    $callMeMaybe = isset($call[1]) ? $call[1] : [];
                    $params = $this->prepareParams($class->getMethod($call[0]), $shareRule)->__invoke($this->expand($callMeMaybe));
                    $object->{$call[0]}(...$params);
                }
                return $object;
            };
        }

        $this->closures[$id] = $closure;
        return $this->closures[$id]($args, $share);
    }

    /**
     * Makes a closure that can generate a fresh instance of $id later.
     */
    private function prepare($id, array $rule, \ReflectionClass $class)
    {
        $constructor = $class->getConstructor();
        // Create parameter-generating closure in order to cache reflection on the parameters.
        // This way $reflectmethod->getParameters() only ever gets called once.
        $params = ($constructor) ? $this->prepareParams($constructor, $rule) : null;

        // Get a closure based on the type of object being created: shared, normal, or constructorless
        if (isset($rule['shared']) && $rule['shared'] === true) {
            return function(array $args, array $share) use ($id, $class, $constructor, $params) {
                // Shared instance: create without calling constructor (and write to \$name and $name, see issue #68)
                $this->instances[$id] = $class->newInstanceWithoutConstructor();

                // Now call constructor after constructing all dependencies. Avoids problems with cyclic references (issue #7)
                if ($constructor) {
                    $constructor->invokeArgs($this->instances[$id], $params($args, $share));
                }
                $this->instances[\ltrim($id, '\\')] = $this->instances[$id];
                return $this->instances[$id];
            };
        }
        if ($params) {
            // This class has dependencies, call the $params closure to generate them based on $args and $share
            return function(array $args, array $share) use ($class, $params) {
                $cn = $class->name;
                return new $cn(...$params($args, $share));
            };
        }
        return function() use ($class) {
            // No constructor arguments, just instantiate the class
            $cn = $class->name;
            return new $cn();
        };
    }

    private function prepareParams(\ReflectionMethod $method, array $rule)
    {
        $paramInfo = []; // Caches some information about the parameter so (slow) reflection isn't needed every time
        foreach ($method->getParameters() as $param) {
            // get the class hint of each param, if there is one
            $class = ($class = $param->getClass()) ? $class->name : null;
            // determine if the param can be null, if we need to substitute a
            // different class, or if we need to force a new instance for it
            $paramInfo[] = [
                $class,
                $param,
                isset($rule['substitutions']) && \array_key_exists($class, $rule['substitutions']),
            ];
        }

        // Return a closure that uses the cached information to generate the arguments for the method
        return function(array $args, array $share = []) use ($paramInfo, $rule) {
            // Now merge all the possible parameters: user-defined in the rule via constructParams,
            // shared instances, and the $args argument from $this->make()
            if (!empty($share) || isset($rule['constructParams'])) {
                $args = \array_merge(
                    $args,
                    (isset($rule['constructParams'])) ? $this->expand($rule['constructParams'], $share) : [],
                    $share
                );
            }
            $parameters = [];
            // Now find a value for each method parameter
            foreach ($paramInfo as $pi) {
                list($class, $param, $sub) = $pi;
                // First, loop through $args and see if each value can match the current parameter based on type hint
                if (!empty($args)) { // This if statement actually gives a ~10% speed increase when $args isn't set
                    foreach ($args as $i => $arg) {
                        if ($class !== null
                            && ($arg instanceof $class || ($arg === null && $param->allowsNull()))
                        ) {
                            // The argument matches, store and remove from $args so it won't wrongly match another parameter
                            $parameters[] = \array_splice($args, $i, 1)[0];
                            continue 2; //Move on to the next parameter
                        }
                    }
                }
                // When nothing from $args matches but a class is type hinted, create an instance to use, using a substitution if set
                if ($class !== null) {
                    $parameters[] = ($sub)
                        ? $this->expand($rule['substitutions'][$class], $share, true)
                        : $this->get($class, [], $share);
                    continue;
                }
                // Variadic functions will only have one argument. To account for those, append any remaining arguments to the list
                if ($param->isVariadic()) {
                    $parameters = array_merge($parameters, $args);
                    continue;
                }
                // There is no type hint, so take the next available value from $args (and remove from $args to stop it being reused)
                if (!empty($args)) {
                    $parameters[] = $this->expand(\array_shift($args));
                    continue;
                }
                // There's no type hint and nothing left in $args, so provide the default value or null
                $parameters[] = ($param->isDefaultValueAvailable()) ? $param->getDefaultValue() : null;
            }
            return $parameters;
        };
    }

    /**
     * Looks for 'instance' array keys in $param, and when found, returns an object based on the value.
     * See {@link https:// r.je/dice.html#example3-1}
     *
     * @param string|array $param
     * @param array $share Whether this class instance will be passed around each time
     * @param bool $createFromString
     * @return mixed
     */
    private function expand($param, array $share = [], $createFromString = false)
    {
        if (!\is_array($param)) {
            // doesn't need any processing
            return (is_string($param) && $createFromString) ? $this->get($param) : $param;
        }
        if (!isset($param['instance'])) {
            // not a lazy instance, so recursively search for any 'instance' keys on deeper levels
            foreach ($param as $name => $value) {
                $param[$name] = $this->expand($value, $share);
            }
            return $param;
        }
        $args = isset($param['params']) ? $this->expand($param['params']) : [];
        // for ['instance' => ['className', 'methodName'] construct the instance before calling it
        if (\is_array($param['instance'])) {
            $param['instance'][0] = $this->expand($param['instance'][0], $share, true);
        }
        if (\is_callable($param['instance'])) {
            // it's a lazy instance formed by a function. Call or return the value stored under the key 'instance'
            if (isset($param['params'])) {
                return \call_user_func_array($param['instance'], $args);
            }
            return \call_user_func($param['instance']);
        }
        if (\is_string($param['instance'])) {
            // it's a lazy instance's class name string
            return $this->get($param['instance'], \array_merge($args, $share));
        }
        // if it's not a string, it's malformed. *shrug*
    }
}
