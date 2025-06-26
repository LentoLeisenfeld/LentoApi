<?php

namespace Lento;

use Lento\Router;
use Lento\Attributes\{Controller, Inject, Ignore, Middleware};

class LentoApi {
    private Router $router;
    private MiddlewareRunner $middleware;
    private array $controllers;
    private array $services;

    public function __construct(array $controllers = [], array $services = []) {
        $this->router = new Router();
        $this->middleware = new MiddlewareRunner();
        $this->controllers = $controllers;
        $this->services = $services;


        Container::register(Router::class, fn() => $this->router);

        $this->registerServices();
        $this->loadControllers();
    }

    private function registerServices(): void {
        foreach ($this->services as $serviceClass) {
            Container::register($serviceClass, fn() => new $serviceClass());
        }
    }

    private function loadControllers(): void {
        foreach ($this->controllers as $controllerClass) {
            $this->loadController($controllerClass);
        }
    }

    public function loadController(string $classname): void {
        $rc = new \ReflectionClass($classname);
        $attr = $rc->getAttributes(Controller::class)[0] ?? null;
        $base = $attr ? $attr->getArguments()[0] : '';

        $controllerInstance = $rc->newInstance();

        // Inject services into properties
        foreach ($rc->getProperties() as $prop) {
            if (!empty($prop->getAttributes(Inject::class))) {
                $type = $prop->getType();
                if ($type) {
                    $instanceToInject = Container::get($type->getName());
                    $prop->setAccessible(true);
                    $prop->setValue($controllerInstance, $instanceToInject);
                }
            }
        }

        // Register routes from methods
        foreach ($rc->getMethods() as $method) {
            if ($method->getAttributes(Ignore::class)) {
                continue;
            }


            foreach ($method->getAttributes() as $attr) {
                $attrName = (new \ReflectionClass($attr->getName()))->getShortName();
                $httpVerbs = ['Get', 'Post', 'Put', 'Delete', 'Ignore'];

                if (in_array($attrName, $httpVerbs)) {
                    $path = $attr->getArguments()[0] ?? '';
                    $middleware = [];

                    foreach ($method->getAttributes(Middleware::class) as $mwAttr) {
                        $middleware[] = $mwAttr->getArguments()[0];
                    }
                    $this->router->addRoute(
                        strtoupper($attrName),
                        $base . $path,
                        [$controllerInstance, $method->getName()],
                        $middleware,
                        name: $method->getName() // or use attribute
                    );
                }
            }
        }
    }

    public function use(callable $middleware): void {
        $this->middleware->use($middleware);
    }

    public function start(): void {
        $request = (object) $_REQUEST;
        $request->router = $this->router;

        $response = (object) [];

        $this->middleware->handle($request, $response, fn() => $this->router->dispatch($request, $response));
    }

    public function getRouter(): Router {
        return $this->router;
    }

    private bool $corsEnabled = false;
private array $corsConfig = [];

public function useCors(array $config = []): void {
    $this->corsEnabled = true;

    // Defaults for CORS
    $defaultConfig = [
        'allowOrigin' => '*',
        'allowMethods' => 'GET, POST, PUT, DELETE, OPTIONS',
        'allowHeaders' => 'Content-Type, Authorization',
        'allowCredentials' => false,
        'maxAge' => 86400,
    ];

    $this->corsConfig = array_merge($defaultConfig, $config);

    // Register middleware that adds CORS headers
    $this->use(function($req, $res, $next) {
        header('Access-Control-Allow-Origin: ' . $this->corsConfig['allowOrigin']);
        header('Access-Control-Allow-Methods: ' . $this->corsConfig['allowMethods']);
        header('Access-Control-Allow-Headers: ' . $this->corsConfig['allowHeaders']);
        if ($this->corsConfig['allowCredentials']) {
            header('Access-Control-Allow-Credentials: true');
        }
        header('Access-Control-Max-Age: ' . $this->corsConfig['maxAge']);

        // Handle OPTIONS preflight immediately
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            exit;
        }

        return $next($req, $res);
    });
}

}
