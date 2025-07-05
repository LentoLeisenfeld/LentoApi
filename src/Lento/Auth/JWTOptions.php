<?php

namespace Lento\Auth;

class JWTOptions
{
    public string $secret = 'default';
    public string $alg = 'HS256';
    public int $ttl = 3600;
    public string $tokenType = 'Bearer';
    public string $header = 'Authorization';

    public function __construct(array $opts = [])
    {
        foreach ($opts as $k => $v) {
            if (property_exists($this, $k)) {
                $this->$k = $v;
            }
        }
    }
}
