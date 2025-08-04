<?php

namespace Lento\OpenAPI;

use RuntimeException;
use Lento\Http\Attributes\Get;
use Lento\Formatter\Attributes\FileFormatter;
use Lento\Routing\Attributes\{Inject, Controller};
use Lento\OpenAPI\OpenAPIGenerator;
use Lento\OpenAPI\Attributes\Ignore;
use Lento\Routing\Router;
use Lento\Exceptions\NotFoundException;

/**
 *
 */
#[Ignore]
#[Controller('/openapi')]
class OpenAPIController
{
    /**
     * Undocumented variable
     *
     * @var Router
     */
    #[Inject]
    protected Router $router;

    const docname = 'documentation';

    /**
     * Undocumented function
     *
     * @return string
     */
    #[Get('/' . self::docname)]
    #[FileFormatter(filename: self::docname . '.html', mimetype: 'text/html', download: false)]
    public function index(): string
    {
        $filename = 'swagger.html';
        $baseDir = __DIR__;
        $safeName = basename($filename);
        $path = "$baseDir/$safeName";

        if (!is_file($path) || !is_readable($path)) {
            throw new NotFoundException("...");
        }

        return file_get_contents($path);
    }

    /**
     * Undocumented function
     *
     * @return array
     */
    #[Get('/documentation.json')]
    #[FileFormatter(filename: 'documentation.json', mimetype: 'application/json', download: false)]
    public function spec(): array
    {
        if (!$this->router) {
            throw new RuntimeException("Router not injected");
        }

        $OpenAPI = new OpenAPIGenerator($this->router);

        return $OpenAPI->generate();
    }
}
