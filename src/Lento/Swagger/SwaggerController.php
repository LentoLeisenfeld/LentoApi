<?php

namespace Lento\Swagger;

use Lento\Attributes\Methods\{Get, Post};
use Lento\Attributes\{Ignore, Inject};
use Lento\{SwaggerGenerator, Router};


#[Ignore]
#[Controller]
class SwaggerController
{
    #[Inject]
    private Router $router;

    #[Get('/apidocs')]
    public function index()
    {
        // Serve the Swagger UI HTML page
        header('Content-Type: text/html');
        echo file_get_contents(__DIR__ . '/swagger.html');
        exit;
    }

    #[Get('/swagger.json')]
    public function spec()
    {
        if (!$this->router) {
            throw new \RuntimeException("Router not injected");
        }

        $swagger = new SwaggerGenerator($this->router);

        $spec = $swagger->generate();
        header('Content-Type: application/json');
        echo json_encode($spec, JSON_PRETTY_PRINT);
        exit;
    }
}
