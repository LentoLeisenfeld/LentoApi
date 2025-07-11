<?php

namespace Lento\Auth;

/**
 * Undocumented class
 */
class JWTOptions
{
    /**
     * Undocumented variable
     *
     * @var string
     */
    public string $secret = 'default';

    /**
     * Undocumented variable
     *
     * @var string
     */
    public string $alg = 'HS256';

    /**
     * Undocumented variable
     *
     * @var integer
     */
    public int $ttl = 3600;

    /**
     * Undocumented variable
     *
     * @var string
     */
    public string $tokenType = 'Bearer';

    /**
     * Undocumented variable
     *
     * @var string
     */
    public string $header = 'Authorization';

    /**
     * Undocumented function
     *
     * @param array $opts
     */
    public function __construct(array $opts = [])
    {
        foreach ($opts as $k => $v) {
            if (property_exists($this, $k)) {
                $this->$k = $v;
            }
        }
    }
}
