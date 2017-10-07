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

class ClosureDispatcher implements DispatcherInterface
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
        if ($container) {
             return call_user_func_array($this->handler, [$container] + $this->params);
        }
        return call_user_func_array($this->handler, $this->params);
    }
}