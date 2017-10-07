<?php
namespace Foundation\Routing;


use Foundation\Routing\Exceptions\NotFoundException;
use Foundation\Routing\Exceptions\BadHttpMethodException;
use Closure;

class Router {

    //:params - only at the end of uri
    // \1 -back reference
    // ?P<something> - named key match
    protected $placeholders = [
        ":int?" => "(\/(?P<\\1>[0-9]+)?)",
        ":int" => "(?P<\\1>[0-9]+)",
        ":page" => "(\/(?P<\\1>[0-9]+)?)",
        ":module" => "([a-zA-Z0-9\-]+)",
        ":controller" => "(?P<\\1>[a-zA-Z0-9\-]+)",
        ":action" => "(?P<\\1>[a-zA-Z0-9\-]+)",
        ":params" => "(?P<\\1>[a-zA-Z0-9\-\/]+)*"
    ];

    /**
     * @var string
     */
    protected $cache_path = "";

    /**
     * @var string
     */
    protected $cache_filename = "routes.json";

    /**
     * @var string
     */
    protected $routes_path = "";

    /**
     * @var string
     */
    protected $routes_filename = "routes.php";

    /**
     * @var string
     */
    protected $namespace = "App\\Controllers\\";

    /**
     * @var string
     */
    protected $controller;

    /**
     * @var string
     */
    protected $action;

    /**
     * @var array
     */
    protected $params;

    /**
     * @var array
     */
    protected $patterns;

    /**
     * @var string
     */
    protected $url;

    /**
     * @var array
     */
    protected $routes;

    /**
     * @var array
     */
    protected $matches;

    /**
     * @var array
     */
    protected $matched_route;

    /**
     * @var string
     */
    protected $http_method;

    /**
     * @var array
     */
    protected $events = [
        'before_match' => null,
        'after_match' => null
    ];

    public function __construct($load_routes = false)
    {
        if (defined("APP_PATH")) {
            $this->setRoutesPath(APP_PATH.DIRECTORY_SEPARATOR);
            $this->setCachePath(APP_PATH.DIRECTORY_SEPARATOR."storage");
        }

        $this->patterns = [];

        $this->params = [];

        $this->routes = [];

        if ($load_routes && !$this->routesAreCached()) {
            $this->collectRoutes();
        }
    }

    /**
     * Takes a $_SERVER['REQUEST_URI'] without the query string or trailing slash
     * and matches it to the registered routes. If the uri is matched returns a
     * Dispatcher instance
     *
     * @param string $request_uri
     * @return DispatcherInterface
     */
    public function catch(string $request_uri = null)
    {
        $this->url = $request_uri ?: $this->getUri();

        if (isset($this->events['before_match'])) {
            $this->events['before_match']($this);
        }

        $this->match();

        if (isset($this->events['after_match'])) {
            $this->events['after_match']($this);
        }

        if ($this->matchedRouteHasClosure()) {
            $this->setParams();
            return new ClosureDispatcher($this->getClosure(), $this->getParams());
        }

        $this->setController();
        $this->setAction();
        $this->setParams();

        return new ControllerDispatcher($this->getController(), $this->getAction(), $this->getParams());
    }

    /**
     * @return bool
     * @throws NotFoundException
     */
    public function match()
    {
        foreach ($this->patterns as $pattern) {

            if (preg_match($pattern, $this->url, $matches)) {

                $http_method = $this->httpMethod();

                if (isset($this->routes[$pattern][$http_method])) {

                    $this->matched_route = $this->routes[$pattern][$http_method];

                    $this->matches = $matches;

                    unset($this->matches[0]);
                    return true;
                } else {
                    throw new BadHttpMethodException("Bad HTTP method for [{$this->url}]", 405);
                }

            }
        }
        throw new NotFoundException("Page not found", 404);
    }

    /**
     * @return \Closure|null
     */
    public function getClosure()
    {
        return $this->matchedRouteHasClosure() ? $this->matched_route['closure'] : null;
    }

    /**
     * @return string
     */
    public function getController()
    {
        return $this->namespace.$this->controller;
    }

    /**
     * @return string
     */
    public function getAction()
    {
        return $this->action;
    }

    /**
     * @return array
     */
    public function getParams()
    {
        return $this->params;
    }

    /**
     * @param null $key
     * @return array|mixed|null
     */
    public function matchedRoute($key = null)
    {
        if ($key) {
            return isset($this->matched_route[$key]) ? $this->matched_route[$key] : null;
        }
        return $this->matched_route;
    }

    /**
     * @return bool
     */
    public function matchedRouteHasClosure()
    {
        return isset($this->matched_route['closure']);
    }

    /**
     * @param string|null $filepath
     * @return bool
     * @throws \Exception
     */
    public function collectRoutes(string $filepath = null)
    {
        $routes_file = $filepath ?: $this->routesFile();

        if (file_exists($routes_file)) {
            $routes = require $routes_file;

            foreach ($routes as $route) {

                $pattern = $this->urlToPattern(trim($route['url'], '/'));

                if (key_exists(0, $route)) {

                    $params = explode('::', $route[0]);

                    unset($route[0]);

                    $route['controller'] = $params[0];
                    $route['action'] = $params[1];

                }

                $http_method = $route['method'];
                unset($route['method']);

                if (is_string($http_method)) {
                    $http_method = strtoupper($http_method);

                    if ($http_method == 'ANY') {

                        $this->routes[$pattern] = [
                            'GET' => $route,
                            'POST' => $route,
                            'PUT' => $route,
                            'PATCH' => $route,
                            'DELETE' => $route,
                            'OPTION' => $route
                        ];

                    }
                    $this->routes[$pattern][$http_method] = $route;
                }

                if (is_array($http_method)) {
                    foreach ($http_method as $key => $value) {
                        $this->routes[$pattern][strtoupper($value)] = $route;
                    }
                }

            }
            return true;
        }
        throw new \Exception('[routes.php] file couldn\'t be found, location should be in the root of the project.');
    }

    /**
     * @return bool
     */
    protected function setController()
    {

        if (key_exists('controller', $this->matched_route)) {

            if (is_string($this->matched_route['controller'])) {
                $this->controller = $this->matched_route['controller'];
                return true;
            }

            if (is_int($this->matched_route['controller'])) {
                $i = $this->matched_route['controller'];
                $this->controller = $this->formatControllerName($this->matches[$i]);
                unset($this->matches[$i]);
                return true;
            }

        } else {

            if (key_exists('controller', $this->matches)) {

                $this->controller = $this->formatControllerName($this->matches['controller']);
                unset($this->matches['controller']);
                return true;

            } else {

                $i = 0;
                foreach ($this->matches as $key => $value) {
                    if (is_string($key) && $i < 1) {
                        $this->controller = $this->formatControllerName($value);
                        unset($this->matches[$key]);
                        return true;
                    }
                    $i++;
                }

                $this->controller = $this->formatControllerName($this->matches[1]);
                unset($this->matches[1]);
                return true;
            }

        }

    }

    /**
     * @param $url_part
     * @return string
     */
    protected function formatControllerName($url_part)
    {
        $controller = explode('-', $url_part);

        $l = count($controller);
        for ($i = 0; $i < $l; $i++) {
            $controller[$i] = ucfirst(strtolower($controller[$i]));
        }

        return implode($controller).'Controller';

    }

    protected function setAction()
    {

        if (key_exists('action', $this->matched_route)) {

            if (is_string($this->matched_route['action'])) {
                $this->action = $this->matched_route['action'];
                return true;
            }

            if (is_int($this->matched_route['action'])) {
                $i = $this->matched_route['action'];
                $this->action = $this->formatActionName($this->matches[$i]);
                unset($this->matches[$i]);
                return true;
            }

        } else {

            if (key_exists('action', $this->matches)) {

                $this->action = $this->formatActionName($this->matches['action']);
                return true;

            } else {

                $i = 0;
                foreach ($this->matches as $key => $value) {
                    if (is_string($key) && $i < 1) {
                        $this->action = $this->formatControllerName($value);
                        unset($this->matches[$key]);
                        return true;
                    }
                    $i++;
                }

                $this->action = $this->formatActionName($this->matches[2]);
                unset($this->matches[2]);
                return true;
            }

        }
    }

    /**
     * @param $url_part
     * @return string
     */
    protected function formatActionName($url_part)
    {
        $action = explode('-', $url_part);

        $l = count($action);

        if ($l > 1) {
            for ($i = 0; $i < $l; $i++) {
                $action[$i] = strtolower($action[$i]);
                if ($i % 2 === 0) {
                    $action[$i] = ucfirst($action[$i]);
                }
            }
        } else {
            return strtolower(implode($action));
        }

        return implode($action);
    }

    protected function setParams()
    {
        if (key_exists('params', $this->matched_route)) {

            if (is_array($this->matched_route['params'])) {

                foreach ($this->matched_route['params'] as $key) {
                    if (isset($this->matches[$key])) {
                        array_push($this->params, $this->matches[$key]);
                    }
                }
                return true;
            }

            if (is_int($this->matched_route['params'])) {
                $i = $this->matched_route['params'];
                array_push($this->params, $this->matches[$i]);
                return true;
            }

        } else {

            if (key_exists('params', $this->matches)) {
                if (strpos($this->matches['params'], '/') !== false) {
                    $this->params = explode('/', $this->matches['params']);
                    return true;
                }
            }

            //string params
            foreach ($this->matches as $key => $match) {
                if (is_string($key)) {
                    $this->params[$key] = $match;
                }
                if (strpos($key, 'int') !== false) {
                    $this->params[$key] = intval($match);
                }
            }
            return true;
        }
    }

    /**
     * @param $qs
     */
    protected function parseQueryString($qs)
    {
        $qs = explode('&', $qs);

        $params = [];

        foreach ($qs as $kv) {
            $kv = explode('=', $kv);
            $params[$kv[0]] = $kv[1];
        }

        array_push($this->params, $params);
    }

    /**
     * @param $url
     * @return mixed|string
     */
    protected function urlToPattern($url)
    {
        if ($url != "" && strpos($url, ':') === false && strpos($url, '{') === false) {
            $url = explode('/', $url);

            foreach ($url as $key => $value) {
                $url[$key] = '('.$value.')';
            }

            $pattern = '/^'.implode('\/', $url).'$/i';

            $this->patterns[] = $pattern;

            return $pattern;
        }

        $pattern = preg_replace('/\//', '\\/', $url);

        // Placeholders
        //$pattern = str_replace('<:action>','<action>', $pattern);
        if (strpos($pattern, ":") !== false) {
            foreach ($this->placeholders as $placeholder => $replace) {
                if (strpos($pattern, $placeholder) !== false) {
                    $pattern = preg_replace('/:('.ltrim($placeholder, ':').')/', $replace, $pattern);
                    break;
                }
            }
        }

        if (strpos($pattern, "{") !== false) {
            // Variables
            // \{(\w+)\} - za escape-ovanje \{ \}
            $pattern = preg_replace('/\{(\w+)\}/', '(?P<\1>[a-zA-Z0-9\-]+)', $pattern);
            // Variables with custom regex
            $pattern = preg_replace('/\{([a-z]+):([^\}]+)\}/','(?P<\1>\2)', $pattern);
        }

        $pattern = '/^'.$pattern.'$/i';

        $this->patterns[] = $pattern;

        return $pattern;
    }

    /**
     * @param $http_method
     * @param $url
     * @param $route_params
     */
    public function addRoute($http_method, $url, $route_params)
    {
        $url_pattern = $this->urlToPattern(trim($url, '/'));

        if (is_string($route_params)) {
            $route_params = $this->parseStringRouteParams($route_params);
        }

        if (is_object($route_params) && $route_params instanceof Closure) {
            $route_params = ['closure' => $route_params];
        }

        $route_params = ['url' => $url] + $route_params;

        if (is_string($http_method)) {
            $http_method = strtoupper($http_method);

            if ($http_method == 'ANY') {

                $this->routes[$url_pattern] = [
                    'GET' => $route_params,
                    'POST' => $route_params,
                    'PUT' => $route_params,
                    'PATCH' => $route_params,
                    'DELETE' => $route_params,
                    'OPTION' => $route_params
                ];

            }
            $this->routes[$url_pattern][$http_method] = $route_params;
        }

        if (is_array($http_method)) {
            foreach ($http_method as $key => $value) {
                $this->routes[$url_pattern][strtoupper($value)] = $route_params;
            }
        }
    }

    /**
     * @param $route_params
     * @return array
     */
    protected function parseStringRouteParams($route_params)
    {
        $route_params = explode('::', $route_params);

        return [
            'controller' => $route_params[0],
            'action' => $route_params[1]
        ];
    }

    /**
     * @param $url
     * @param $route_params
     */
    public function get($url, $route_params)
    {
        $this->addRoute('GET', $url, $route_params);
    }

    /**
     * @param $url
     * @param $route_params
     */
    public function post($url, $route_params)
    {
        $this->addRoute('POST', $url, $route_params);
    }

    /**
     * @param $url
     * @param $route_params
     */
    public function put($url, $route_params)
    {
        $this->addRoute('PUT', $url, $route_params);
    }

    /**
     * @param $url
     * @param $route_params
     */
    public function patch($url, $route_params)
    {
        $this->addRoute('PATCH', $url, $route_params);
    }

    /**
     * @param $url
     * @param $route_params
     */
    public function delete($url, $route_params)
    {
        $this->addRoute('DELETE', $url, $route_params);
    }

    /**
     * Retrieves sanitized URI of the request and with trimmed trailing slashes
     *
     * @return mixed
     */
    public function getUri()
    {
       if (isset($_SERVER['QUERY_STRING'])) {
           return filter_var(trim(str_replace('?'.$_SERVER['QUERY_STRING'], '', $_SERVER['REQUEST_URI']), '/'), FILTER_SANITIZE_URL);
       }
       return filter_var(trim($_SERVER['REQUEST_URI'], '/'), FILTER_SANITIZE_URL);
    }

    /**
     * @return mixed
     */
    protected function httpMethod()
    {
        return $_SERVER['REQUEST_METHOD'];
    }

    /**
     * @return bool
     */
    protected function routesAreCached()
    {
        return (bool) file_exists($this->cacheFile());
    }

    /**
     * @param Closure|null $callback
     */
    public function cache(Closure $callback = null)
    {
        if (!$this->routesAreCached()) {
            if (empty($this->routes)) {
                if ($callback != null) {
                    $callback($this);
                }
            }
            $this->saveRoutes();
        }
        $cache = json_decode($this->loadFromCache(), true);

        $this->patterns = $cache['patterns'];
        $this->routes = $cache['routes'];
    }
    
    protected function saveRoutes()
    {
        if (!is_dir($this->cache_path)) {
            mkdir($this->cache_path, 0777);
        }

        $table = fopen($this->cacheFile(), 'w');

        fwrite($table, json_encode([
            'routes' => $this->routes,
            'patterns' => $this->patterns
        ]));
    }

    /**
     * @return bool|string
     */
    protected function loadFromCache()
    {
        if ($this->routesAreCached()) {
            $cache = $this->cacheFile();
            return fread(
                fopen($cache, 'r'),
                filesize($cache)
            );
        }
        return false;
    }

    /**
     * @return array
     */
    public function getRoutes()
    {
        return $this->routes;
    }

    public function printRoutes()
    {
        print '<pre>'.htmlspecialchars(print_r($this->getRoutes(), true)).'</pre>';
    }

    /**
     * @param $key
     * @param callable $handler
     */
    public function addEventListener($key, callable $handler)
    {
        if ($key == 'before_match' || $key == 'after_match') {
            $this->events[$key] = $handler;
        }
    }

    /**
     * @param $namespace
     */
    public function setNamespace($namespace)
    {
        $this->namespace = $namespace;
    }

    /**
     * @param string $path
     */
    public function setCachePath(string $path)
    {
        $this->cache_path = $path;
    }

    /**
     * @param string $path
     */
    public function setRoutesPath(string $path)
    {
        $this->routes_path = $path;
    }

    /**
     * @return string
     */
    public function cacheFile()
    {
        return $this->cache_path.DIRECTORY_SEPARATOR.$this->cache_filename;
    }

    /**
     * @return string
     */
    public function routesFile()
    {
        return $this->routes_path.DIRECTORY_SEPARATOR.$this->routes_filename;
    }
}


