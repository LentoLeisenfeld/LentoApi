<?php

namespace Lento\Http;

class Request
{
    public string $method;
    public string $path;
    public array $headers = [];
    public array $query = [];
    public array $body = [];
    public mixed $jwt = null;

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

        // Headers (SAPI-agnostic)
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = str_replace(
                    ' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($key, 5))))
                );
                $req->headers[$name] = $value;
            }
        }
        // "Authorization" fallback
        if (isset($_SERVER['AUTHORIZATION'])) {
            $req->headers['Authorization'] = $_SERVER['AUTHORIZATION'];
        }

        $req->query = $_GET;

        // Parse JSON or form data, prefer JSON if present
        $raw = file_get_contents('php://input');
        $req->body = [];
        if ($raw && ($data = json_decode($raw, true))) {
            $req->body = $data;
        } elseif ($_POST) {
            $req->body = $_POST;
        }

        return $req;
    }

    public function query(string $key, $default = null)
    {
        return $this->query[$key] ?? $default;
    }

    public function body(string $key = null, $default = null)
    {
        if ($key === null) return $this->body;
        return $this->body[$key] ?? $default;
    }

    public function input(string $key, $default = null)
    {
        return $this->body($key, $default);
    }
}
