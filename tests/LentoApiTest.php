<?php

use PHPUnit\Framework\TestCase;
use Lento\LentoApi;
use Lento\Router;
use Lento\Attributes\Controller;
use Lento\Attributes\Methods\Get;

#[Controller('/hello')]
class DummyController {
    #[Get('/index')]
    public function getIndex() {
        return 'Hello World';
    }
}

class LentoApiTest extends TestCase {
    public function testLentoApiRegistersRoutes() {
        $api = new LentoApi(controllers: [DummyController::class]);

        $router = $api->getRouter();
        $routes = $router->getRoutes();

        $this->assertNotEmpty($routes);
        $this->assertEquals('/hello/index', $routes[0]->path);
        $this->assertEquals('GET', $routes[0]->method);
    }

    public function testApiDispatches() {
        $_REQUEST['__test'] = true;
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/hello/index';

        $api = new LentoApi(controllers: [DummyController::class]);

        ob_start();
        $api->start();
        $output = ob_get_clean();

        $this->assertStringContainsString('Hello', $output);
    }
}
