<?php
/**
 * Created by PhpStorm.
 * User: sasablagojevic
 * Date: 10/4/17
 * Time: 4:50 PM
 */

namespace Foundation\Routing;


use Psr\Container\ContainerInterface;


class ControllerDispatcher implements DispatcherInterface
{

    protected $controller;

    protected $action;

    protected $params;

    public function __construct($controller, string $action, array $params)
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
        if ($container) {
            $controller = is_object($this->controller) ? $this->controller : $container->get($this->controller);

            $reflection = new \ReflectionClass($controller);

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

            //return $reflection->getMethod($method)->invokeArgs($class_instance, $params);
            try {
                return call_user_func_array(array($controller, $this->action), $params + $this->params);
            } catch (\BadMethodCallException $e) {
                throw $e;
            }
        }

        return call_user_func_array(
            [
                is_object($this->controller) ? $this->controller : new $this->controller(),
                $this->action
            ],
            $this->params
        );
    }
}