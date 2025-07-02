<?php

namespace Lento\Http;

/**
 * Represents an HTTP response.
 */
class Response
{
    private int $status = 200;
    private array $headers = [];
    private string $body = '';

    public function withHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function status(int $code): self
    {
        $this->status = $code;
        return $this;
    }

    public function write(string $data): self
    {
        $this->body .= $data;
        return $this;
    }

    /**
     * Send headers and body to the client.
     */
    public function send(): void
    {
        if (!headers_sent()) {
            // Set HTTP status code
            http_response_code($this->status);

            // Automatically add Content-Length header if not provided
            if (!isset($this->headers['Content-Length'])) {
                header('Content-Length: ' . strlen($this->body));
            }

            // Send all custom headers
            foreach ($this->headers as $name => $value) {
                header("{$name}: {$value}");
            }
        }

        // Output the body
        echo $this->body;
        // Terminate to prevent further output
        exit;
    }
}