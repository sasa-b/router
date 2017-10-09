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

    public function __construct(Closure $handler, array $params)
    {
        $this->handler = $handler;
        $this->params = $params;
    }

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

    public function beforeDispatch(ContainerInterface $container = null)
    {
        if (isset($this->events['after_dispatch'])) {
            $this->events['after_dispatch']($container);
        }
    }

    public function afterDispatch(ContainerInterface $container = null)
    {
        if (isset($this->events['after_dispatch'])) {
            $this->events['after_dispatch']($container);
        }
    }
}