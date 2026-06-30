<?php

namespace TypechoPlugin\TypechoPay\Support;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

final class CardCodeCipher
{
    private const CIPHER = 'aes-256-gcm';
    private const AAD = 'typechopay-cardcode-v1';

    public static function encrypt(string $plaintext, string $keyMaterial): string
    {
        if ($plaintext === '') {
            throw new \InvalidArgumentException('Card code plaintext cannot be empty.');
        }

        if (!function_exists('openssl_encrypt')) {
            throw new \RuntimeException('PHP OpenSSL extension is required for card-code encryption.');
        }

        $iv = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt(
            $plaintext,
            self::CIPHER,
            self::key($keyMaterial),
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            self::AAD,
            16
        );

        if ($ciphertext === false || $tag === '') {
            throw new \RuntimeException('Failed to encrypt card code.');
        }

        return 'v1:' . base64_encode($iv . $tag . $ciphertext);
    }

    public static function decrypt(string $encoded, string $keyMaterial): string
    {
        if (strpos($encoded, 'v1:') !== 0) {
            throw new \RuntimeException('Unsupported card-code ciphertext version.');
        }

        if (!function_exists('openssl_decrypt')) {
            throw new \RuntimeException('PHP OpenSSL extension is required for card-code decryption.');
        }

        $payload = base64_decode(substr($encoded, 3), true);
        if ($payload === false || strlen($payload) <= 28) {
            throw new \RuntimeException('Invalid card-code ciphertext.');
        }

        $iv = substr($payload, 0, 12);
        $tag = substr($payload, 12, 16);
        $ciphertext = substr($payload, 28);
        $plaintext = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            self::key($keyMaterial),
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            self::AAD
        );

        if ($plaintext === false) {
            throw new \RuntimeException('Failed to decrypt card code.');
        }

        return $plaintext;
    }

    private static function key(string $keyMaterial): string
    {
        if ($keyMaterial === '') {
            throw new \InvalidArgumentException('Card-code encryption key is missing.');
        }

        return hash('sha256', 'typechopay-cardcode:' . $keyMaterial, true);
    }
}
