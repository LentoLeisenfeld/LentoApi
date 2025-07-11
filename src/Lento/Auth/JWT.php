<?php

namespace Lento\Auth;

/**
 * Undocumented class
 */
class JWT
{
    /**
     * Undocumented variable
     *
     * @var JWTOptions
     */
    private static JWTOptions $options;

    /**
     * Undocumented function
     *
     * @param array|JWTOptions $opts
     * @return void
     */
    public static function configure(array|JWTOptions $opts): void
    {
        self::$options = $opts instanceof JWTOptions ? $opts : new JWTOptions($opts);
    }

    /**
     * Undocumented function
     *
     * @return JWTOptions
     */
    public static function options(): JWTOptions
    {
        if (!isset(self::$options)) {
            self::$options = new JWTOptions();
        }

        return self::$options;
    }

    /**
     * Undocumented function
     *
     * @param array $payload
     * @param integer|null $ttl
     * @return string
     */
    public static function encode(array $payload, ?int $ttl = null): string
    {
        $opts = self::options();
        $header = ['alg' => $opts->alg, 'typ' => 'JWT'];
        $payload['exp'] = time() + ($ttl ?? $opts->ttl);

        $h = rtrim(strtr(base64_encode(json_encode($header)), '+/', '-_'), '=');
        $p = rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');
        $sig = hash_hmac('sha256', "$h.$p", $opts->secret, true);
        $s = rtrim(strtr(base64_encode($sig), '+/', '-_'), '=');
        return "$h.$p.$s";
    }

    /**
     * Undocumented function
     *
     * @param string $jwt
     * @return array|null
     */
    public static function decode(string $jwt): ?array
    {
        $opts = self::options();
        if (substr_count($jwt, '.') !== 2) {
            return null;
        }
        [$h, $p, $s] = explode('.', $jwt);
        $sig = hash_hmac('sha256', "$h.$p", $opts->secret, true);
        $validSig = rtrim(strtr(base64_encode($sig), '+/', '-_'), '=');
        if (!hash_equals($validSig, $s)) {
            return null;
        }
        $payload = json_decode(base64_decode(strtr($p, '-_', '+/')), true);
        if (!$payload || (isset($payload['exp']) && $payload['exp'] < time())) {
            return null;
        }
        return $payload;
    }

    /**
     * Parses the JWT from the given HTTP headers (according to configured header and token type).
     * Returns payload or null.
     *
     * @param array $headers
     * @return array|null
     */
    public static function fromRequestHeaders(array $headers): ?array
    {
        $opts = self::options();
        $headerName = $opts->header;
        $value = null;
        foreach ($headers as $k => $v) {
            if (strcasecmp($k, $headerName) === 0) {
                $value = is_array($v) ? $v[0] : $v;
                break;
            }
        }
        if (!$value) {
            return null;
        }
        $prefix = $opts->tokenType . ' ';
        if (strncasecmp($value, $prefix, strlen($prefix)) === 0) {
            $jwt = trim(substr($value, strlen($prefix)));
            return self::decode($jwt);
        }
        return null;
    }
}
