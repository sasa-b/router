<?php
/**
 * Created by PhpStorm.
 * User: sasablagojevic
 * Date: 10/4/17
 * Time: 3:17 PM
 */

namespace Foundation\Routing;


use Psr\Container\ContainerInterface;
use Closure;

class ClosureDispatcher extends Dispatcher implements DispatcherInterface
{
    protected $params;

    protected $handler;

    /**
     * ClosureDispatcher constructor.
     * @param Closure $handler
     * @param array $params
     */
    public function __construct(Closure $handler = null, array $params = [])
    {
        $this->handler = $handler;
        $this->params = [];
    }

    /**
     * @param ContainerInterface|null $container
     * @return mixed
     */
    public function dispatch(ContainerInterface $container = null)
    {
        $this->beforeDispatch($container);

        if ($container) {
             $called = call_user_func_array($this->handler, [$container] + $this->params);
        } else {
            $called = call_user_func_array($this->handler, $this->params);
        }

        $this->afterDispatch($container);

        return $called;
    }

    /**
     * @param callable $handler
     * @return $this
     */
    public function handler(callable $handler)
    {
        $this->handler = $handler;
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
            $this->events['before_dispatch']($container);
        }
    }

    /**
     * @param ContainerInterface|null $container
     */
    public function afterDispatch(ContainerInterface $container = null)
    {
        if (isset($this->events['after_dispatch'])) {
            $this->events['after_dispatch']($container);
        }
    }
}