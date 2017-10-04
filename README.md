# Router
Lightweight router inspired by Laravel's and Phalcon's router.
*********
## Usage examples
```php
$router = new \Foundation\Routing\Router();

// here we are utilising cache for performance, if the cache file was not found 
// routes will be registered and the cache file recreated
$router->cache(function (\Foundation\Routing\Router $r) {
    // if you want to collect routes from a file
    // you can set the path to the routes file as a paramater to collectRoutes() method
    // or via setRoutesPath() method
    $r->collectRoutes();
    
    // you can both collect routes and add them one by one, they will be merged
    $r->get('/foo/{bar}', [
        'controller' => 'FooController',
        'action' => 'index',
    ]);
    
    $r->post('/foo/{bar}', 'FooController::store');

    $r->get('/foo', function () {
        echo 'Hello foo!';
    });
});

try {
    // Adding event listeners
    $router->addEventListener('before_match', function(\Foundation\Routing\Router $router) {
        echo "before match";
    });

    $router->addEventListener('after_match', function(\Foundation\Routing\Router $router) {
        echo "after match";
    });

    $dispatcher = $router->catch();

    $dispatcher->dispatch();

} catch (\Foundation\Routing\Exceptions\NotFoundException $e) {
    echo $e->getCode() . " - Page not found";
} catch (\Foundation\Routing\Exceptions\BadHttpMethodException $e) {
    echo $e->getCode() . " - Bad Http Method";
} 
```
