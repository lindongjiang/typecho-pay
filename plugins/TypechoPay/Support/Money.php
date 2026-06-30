<?php

namespace TypechoPlugin\TypechoPay\Support;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

final class Money
{
    public static function assertAmount($amount): int
    {
        $value = filter_var($amount, FILTER_VALIDATE_INT);
        if ($value === false || $value <= 0) {
            throw new \InvalidArgumentException('Invalid payment amount.');
        }

        return (int) $value;
    }

    public static function assertCurrency($currency): string
    {
        $value = strtoupper(trim((string) $currency));
        if ($value !== 'CNY') {
            throw new \InvalidArgumentException('Unsupported payment currency.');
        }

        return $value;
    }

    public static function cnyFenToYuan(int $amount): string
    {
        return number_format($amount / 100, 2, '.', '');
    }

    public static function yuanStringToFen(string $amount): int
    {
        $value = trim($amount);
        if (!preg_match('/^\d+(?:\.\d{1,2})?$/', $value)) {
            throw new \InvalidArgumentException('Invalid CNY amount.');
        }

        [$yuan, $fen] = array_pad(explode('.', $value, 2), 2, '');
        $fen = str_pad(substr($fen, 0, 2), 2, '0');

        return ((int) $yuan * 100) + (int) $fen;
    }

    public static function assertCnyYuanAmount($amount): int
    {
        $value = self::yuanStringToFen((string) $amount);
        if ($value <= 0) {
            throw new \InvalidArgumentException('Invalid CNY amount.');
        }

        return $value;
    }

    public static function formatCnyInput(int $amount): string
    {
        return number_format($amount / 100, 2, '.', '');
    }

    public static function formatForDisplay(int $amount, string $currency): string
    {
        $currency = strtoupper($currency);
        if ($currency === 'CNY') {
            return '¥' . number_format($amount / 100, 2, '.', '');
        }

        return $currency . ' ' . $amount;
    }
}
