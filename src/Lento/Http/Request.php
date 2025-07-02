<?php

namespace Lento\Http;

class Request
{
    private string $method;
    private string $path;
    private array $headers = [];
    private array $query = [];
    private array $body = [];
    private ?\Psr\Log\LoggerInterface $logger = null;

    private function __construct() {}

    /**
     * Capture the current HTTP request from globals.
     */
    public static function capture(): self
    {
        $req = new self();
        $req->method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $req->path = parse_url($uri, PHP_URL_PATH) ?: '/';

        // Capture headers
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = str_replace(
                    ' ', '-',
                    ucwords(strtolower(str_replace('_', ' ', substr($key, 5))))
                );
                $req->headers[$name] = $value;
            }
        }

        // Query parameters
        $req->query = $_GET;

        // Body (for JSON)
        $raw = file_get_contents('php://input');
        if ($raw) {
            $data = json_decode($raw, true);
            if (is_array($data)) {
                $req->body = $data;
            }
        }

        return $req;
    }

    /**
     * Get a value from the query string.
     */
    public function query(string $key, $default = null)
    {
        return $_GET[$key] ?? $default;
    }

    /**
     * Get a value from the request body (JSON or form).
     */
    public function body(string $key = null, $default = null)
    {
        // Parse body just once, cache result
        static $data;
        if ($data === null) {
            $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
            if (stripos($contentType, 'application/json') !== false) {
                $data = json_decode(file_get_contents('php://input'), true) ?? [];
            } else {
                $data = $_POST;
            }
        }
        if ($key === null) {
            return $data;
        }
        return $data[$key] ?? $default;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function header(string $name): ?string
    {
        return $this->headers[$name] ?? null;
    }

    public function input(string $key, $default = null)
    {
        return $this->body[$key] ?? $default;
    }

    public function setLogger(\Psr\Log\LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    public function getLogger(): ?\Psr\Log\LoggerInterface
    {
        return $this->logger;
    }
}