<?php

define('__TYPECHO_ROOT_DIR__', dirname(__DIR__, 4));

require_once dirname(__DIR__) . '/Support/Money.php';

use TypechoPlugin\TypechoPay\Support\Money;

function assertSame($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, $message . "\n");
        exit(1);
    }
}

assertSame(1000, Money::yuanStringToFen('10'), 'Expected whole yuan amount to parse.');
assertSame(1001, Money::yuanStringToFen('10.01'), 'Expected cent amount to parse without float math.');
assertSame(1, Money::yuanStringToFen('0.01'), 'Expected smallest CNY amount to parse.');
assertSame(1, Money::assertCnyYuanAmount('0.01'), 'Expected minimum admin amount to be accepted.');
assertSame('5.00', Money::formatCnyInput(500), 'Expected fen amount to render as yuan input.');

try {
    Money::assertCnyYuanAmount('0');
    fwrite(STDERR, "Expected zero CNY amount to throw\n");
    exit(1);
} catch (InvalidArgumentException $e) {
    // Expected.
}

try {
    Money::yuanStringToFen('1.001');
    fwrite(STDERR, "Expected invalid CNY precision to throw\n");
    exit(1);
} catch (InvalidArgumentException $e) {
    // Expected.
}

echo "MoneyTest passed\n";
