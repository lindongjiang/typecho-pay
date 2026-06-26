<?php

define('__TYPECHO_ROOT_DIR__', dirname(__DIR__, 4));

require_once dirname(__DIR__) . '/Support/CardCodeCipher.php';

use TypechoPlugin\TypechoPay\Support\CardCodeCipher;

$key = 'test-card-code-secret';
$plaintext = 'CARD-<script>alert(1)</script>-001';
$ciphertext = CardCodeCipher::encrypt($plaintext, $key);

if ($ciphertext === $plaintext || strpos($ciphertext, 'v1:') !== 0) {
    fwrite(STDERR, "Expected encrypted card code with version prefix\n");
    exit(1);
}

if (CardCodeCipher::decrypt($ciphertext, $key) !== $plaintext) {
    fwrite(STDERR, "Expected card code decrypt round trip\n");
    exit(1);
}

try {
    CardCodeCipher::decrypt($ciphertext, 'wrong-key');
    fwrite(STDERR, "Expected wrong key decrypt to fail\n");
    exit(1);
} catch (RuntimeException $e) {
    // Expected.
}

echo "CardCodeCipherTest passed\n";
