<?php

namespace Lento\Swagger;

use Lento\Attributes\Methods\{Get, Post};
use Lento\Attributes\{Ignore, Inject, Controller};
use Lento\Swagger\{SwaggerGenerator};
use Lento\Routing\Router;
use Lento\Http\{Request, Response};

#[Ignore]
#[Controller('/swagger')]
class SwaggerController
{
    #[Inject]
    private Router $router;


    public function __construct()
    {

    }
    #[Get('/docs')]
    public function index(Request $req, Response $res): Response
    {
        // Serve the Swagger UI HTML page
        //header('Content-Type: text/html');
        //return file_get_contents(__DIR__ . '/swagger.html');


                // Sanitize & build full path
        $filename = 'swagger.html';
        $baseDir = __DIR__;
        $safeName = basename($filename);
        $path = "$baseDir/$safeName";

        if (!is_file($path) || !is_readable($path)) {
            // 404 Not Found
            return $res
                ->status(404)
                ->write('File not found');
        }

        // Determine content type
        $mime = mime_content_type($path) ?: 'text/html';

        // Read file contents
        $content = file_get_contents($path);

        // Add headers and body
        return $res
            ->status(200)
            ->withHeader('Content-Type', $mime)
            ->write($content);
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
