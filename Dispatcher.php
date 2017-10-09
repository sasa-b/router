<?php
/**
 * Created by PhpStorm.
 * User: sasablagojevic
 * Date: 10/4/17
 * Time: 7:19 PM
 */

namespace Foundation\Routing;


abstract class Dispatcher
{
    const BEFORE_DISPATCH = 1;

    const AFTER_DISPATCH = 2;
    /**
     * @var array
     */
    protected $events = [
        'before_dispatch' => null,
        'after_dispatch' => null
    ];

    /**
     * @param int|string $event
     * @param callable $handler
     */
    public function addEventListener($event, callable $handler)
    {
        if ($event == self::BEFORE_DISPATCH || $event == 'before_dispatch') {
            $this->events['before_dispatch'] = $handler;
        } else if ($event == self::AFTER_DISPATCH || $event == 'after_dispatch') {
            $this->events['after_dispatch'] = $handler;
        }
    }
}