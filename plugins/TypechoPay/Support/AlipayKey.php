<?php

namespace TypechoPlugin\TypechoPay\Support;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

final class AlipayKey
{
    public static function body(string $key): string
    {
        $key = trim(str_replace(["\r\n", "\r"], "\n", $key));
        if ($key === '') {
            return '';
        }

        $key = preg_replace('/-----BEGIN [^-]+-----|-----END [^-]+-----/', '', $key) ?: $key;
        return preg_replace('/\s+/', '', $key) ?: '';
    }

    public static function rsaPrivatePem(string $key): string
    {
        return self::pem('RSA PRIVATE KEY', self::body($key));
    }

    public static function publicPem(string $key): string
    {
        return self::pem('PUBLIC KEY', self::body($key));
    }

    public static function canSignWithSdkBody(string $key): bool
    {
        if (!function_exists('openssl_sign')) {
            return false;
        }

        $body = self::body($key);
        if ($body === '') {
            return false;
        }

        $signature = '';
        return @openssl_sign('typechopay-alipay-diagnostic', $signature, self::rsaPrivatePem($body), OPENSSL_ALGO_SHA256) === true
            && $signature !== '';
    }

    public static function canLoadPublicWithSdkBody(string $key): bool
    {
        if (!function_exists('openssl_pkey_get_public')) {
            return false;
        }

        $body = self::body($key);
        return $body !== '' && @openssl_pkey_get_public(self::publicPem($body)) !== false;
    }

    private static function pem(string $type, string $body): string
    {
        return '-----BEGIN ' . $type . "-----\n"
            . rtrim(chunk_split($body, 64, "\n"))
            . "\n-----END " . $type . '-----';
    }
}
