<?php

namespace TypechoPlugin\TypechoPay\Support;

use Typecho\Cookie;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

final class GuestToken
{
    private const COOKIE = '__typechopay_guest';
    private const TTL = 2592000;

    public static function getOrCreate(): string
    {
        $token = self::get();
        if ($token !== null) {
            return $token;
        }

        $token = bin2hex(random_bytes(16));
        Cookie::set(self::COOKIE, $token, time() + self::TTL);

        return $token;
    }

    public static function get(): ?string
    {
        $token = Cookie::get(self::COOKIE);
        if (!is_string($token) || !preg_match('/^[a-f0-9]{32}$/', $token)) {
            return null;
        }

        return $token;
    }

    public static function hash(?string $token): ?string
    {
        return $token === null ? null : hash('sha256', $token);
    }
}
