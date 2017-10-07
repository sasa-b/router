<?php
/**
 * Created by PhpStorm.
 * User: sasablagojevic
 * Date: 10/4/17
 * Time: 7:19 PM
 */

namespace App\src\Routing;


abstract class Dispatcher
{
    /**
     * @var array
     */
    protected $events = [
        'before_dispatch' => null,
        'after_dispatch' => null
    ];
}