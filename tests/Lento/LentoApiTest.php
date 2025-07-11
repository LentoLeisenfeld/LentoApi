<?php

use PHPUnit\Framework\TestCase;
use Lento\LentoApi;
use Lento\Routing\Attributes\Controller;
use Lento\Http\Attributes\Get;
use Lento\Http\{Request, Response};

#[Controller('/hello')]
class DummyController {
    #[Get('/index')]
    public function getIndex(Request $req, Response $res) {
        return $res->write('Hello World')->send();
    }
}

class LentoApiTest extends TestCase {
    public function testLentoApiRegistersRoutes() {
        $_SERVER['REQUEST_URI'] = '/hello/index';
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $api = new LentoApi(controllers: [DummyController::class]);

        $router = $api->getRouter();
        $routes = $router->getRoutes();

        ob_start();
        $api->start();
        $output = ob_get_clean();


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
