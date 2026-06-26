<?php

define('__TYPECHO_ROOT_DIR__', dirname(__DIR__, 4));

require_once dirname(__DIR__) . '/Support/Signer.php';

use TypechoPlugin\TypechoPay\Support\Signer;

$payload = [
    'amount' => '500',
    'currency' => 'JPY',
    'subject' => 'AppFlex 30 day',
    'biz_type' => 'post',
    'biz_id' => '100',
    'gateway' => 'paypay',
    'return_to' => 'https://example.com/post/100.html',
    'ts' => '1780000000',
    'nonce' => '1234567890abcdef',
];

$secret = 'test-secret';
$signature = Signer::sign($payload, $secret);

if (Signer::verify($payload, $secret, $signature) !== true) {
    fwrite(STDERR, "Expected valid signature\n");
    exit(1);
}

$tampered = $payload;
$tampered['amount'] = '1';

if (Signer::verify($tampered, $secret, $signature) !== false) {
    fwrite(STDERR, "Expected tampered signature to fail\n");
    exit(1);
}

$tamperedGateway = $payload;
$tamperedGateway['gateway'] = 'wechat';

if (Signer::verify($tamperedGateway, $secret, $signature) !== false) {
    fwrite(STDERR, "Expected tampered gateway to fail\n");
    exit(1);
}

$tamperedReturnTo = $payload;
$tamperedReturnTo['return_to'] = 'https://evil.example/post/100.html';

if (Signer::verify($tamperedReturnTo, $secret, $signature) !== false) {
    fwrite(STDERR, "Expected tampered return_to to fail\n");
    exit(1);
}

$productPayload = [
    'product_id' => '18',
    'gateway' => 'alipay',
    'return_to' => 'https://example.com/post/100.html',
];
$productSignature = Signer::sign($productPayload, $secret);

if (Signer::verify($productPayload, $secret, $productSignature) !== true) {
    fwrite(STDERR, "Expected product entry signature to pass\n");
    exit(1);
}

$tamperedProduct = $productPayload;
$tamperedProduct['product_id'] = '19';
if (Signer::verify($tamperedProduct, $secret, $productSignature) !== false) {
    fwrite(STDERR, "Expected tampered product id to fail\n");
    exit(1);
}

echo "SignerTest passed\n";
