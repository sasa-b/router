<?php
/**
 * Created by PhpStorm.
 * User: sasablagojevic
 * Date: 10/4/17
 * Time: 4:50 PM
 */

namespace Foundation\Routing;


use Psr\Container\ContainerInterface;


class ControllerDispatcher extends Dispatcher implements DispatcherInterface
{

    protected $controller;

    protected $action;

    protected $params;

    /**
     * ControllerDispatcher constructor.
     * @param $controller
     * @param string $action
     * @param array $params
     */
    public function __construct($controller = null, string $action = '', array $params = [])
    {
        $this->controller = $controller;

        $this->action = $action;

        $this->params = $params;
    }

    /**
     * @param ContainerInterface|null $container
     * @return mixed
     */
    public function dispatch(ContainerInterface $container = null)
    {
        $this->beforeDispatch($container);

        if ($container) {
            $this->controller = is_object($this->controller) ? $this->controller : $container->get($this->controller);

            $reflection = new \ReflectionClass($this->controller);

            $params = [];

            if ($reflection->hasMethod($this->action)) {
                if ($args = $reflection->getMethod($this->action)->getParameters()) {
                    foreach ($args as $arg) {
                        if ($dependency = $arg->getClass()) {
                            $params[$dependency->name] = $container->get($dependency->name);
                        } elseif ($arg->isDefaultValueAvailable()) {
                            $params[$arg->name] = $arg->getDefaultValue();
                        } else {
                            $params[$arg->name] = null;
                        }
                    }
                }
            }

            $this->params = $params + $this->params;
        }

        $called = $this->call($this->controller, $this->action, $this->params);

        $this->afterDispatch($container);

        return $called;
    }

    /**
     * @param $class
     * @param string $method
     * @param array $params
     * @return mixed
     */
    protected function call($class, string $method, array $params)
    {
        try {

            return call_user_func_array(
                [
                    is_object($class) ? $class : new $class(),
                    $method
                ],
                $params
            );

        } catch (\BadMethodCallException $e) {
            throw $e;
        }
    }

    /**
     * @param $controller
     * @return $this
     */
    public function controller($controller)
    {
        $this->controller = $controller;
        return $this;
    }

    /**
     * @param string $action
     * @return $this
     */
    public function action(string $action)
    {
        $this->action = $action;
        return $this;
    }

    /**
     * @param array $params
     * @return $this
     */
    public function params(array $params)
    {
        $this->params = $params;
        return $this;
    }

    /**
     * @param ContainerInterface|null $container
     */
    public function beforeDispatch(ContainerInterface $container = null)
    {
        if (isset($this->events['before_dispatch'])) {
            $this->events['before_dispatch']([$this->controller, $this->action, $this->params], $container);
        }
    }

    /**
     * @param ContainerInterface|null $container
     */
    public function afterDispatch(ContainerInterface $container = null)
    {
        if (isset($this->events['after_dispatch'])) {
            $this->events['after_dispatch']([$this->controller, $this->action, $this->params], $container);
        }
    }
}