<?php

namespace Widget {
    class Options
    {
    }
}

namespace {
    define('__TYPECHO_ROOT_DIR__', dirname(__DIR__, 4));

    require_once dirname(__DIR__) . '/src/Contracts/NotifyResult.php';
    require_once dirname(__DIR__) . '/src/Contracts/PayCreateResult.php';
    require_once dirname(__DIR__) . '/src/Contracts/GatewayInterface.php';
    require_once dirname(__DIR__) . '/src/Support/HttpHeaders.php';
    require_once dirname(__DIR__) . '/src/Gateways/AbstractGateway.php';
    require_once dirname(__DIR__) . '/src/Gateways/PayPayGateway.php';

    use TypechoPlugin\TypechoPay\Gateways\PayPayGateway;

    $config = [
        'paypayApiKey' => 'test-key',
        'paypayApiSecret' => 'test-secret',
        'paypayMerchantId' => 'merchant-123',
        'paypayEnvironment' => 'sandbox',
    ];

    function paypayAuth(array $config, string $rawBody, string $contentType = 'application/json;charset=UTF-8;'): string
    {
        $nonce = 'abcd1234';
        $epoch = (string) time();
        $hash = base64_encode(md5($contentType . $rawBody, true));
        $dataToSign = implode("\n", ['/action/typechopay', 'POST', $nonce, $epoch, $contentType, $hash]);
        $mac = base64_encode(hash_hmac('sha256', $dataToSign, $config['paypayApiSecret'], true));

        return 'hmac OPA-Auth:' . $config['paypayApiKey'] . ':' . $mac . ':' . $nonce . ':' . $epoch . ':' . $hash;
    }

    function assertTrue($condition, string $message): void
    {
        if (!$condition) {
            fwrite(STDERR, $message . "\n");
            exit(1);
        }
    }

    $_SERVER['REQUEST_URI'] = '/action/typechopay?do=notify&gateway=paypay';
    $gateway = new PayPayGateway($config, new \Widget\Options());

    $webhook = [
        'notification_type' => 'Transaction',
        'notification_id' => 'notify-1',
        'merchant_id' => 'merchant-123',
        'merchant_order_id' => 'TP202606260000001234ABCD',
        'order_id' => 'PAYPAY-ORDER-1',
        'order_amount' => '500',
        'state' => 'COMPLETED',
    ];
    $raw = json_encode($webhook, JSON_UNESCAPED_SLASHES);
    $headers = [
        'authorization' => paypayAuth($config, $raw),
        'content-type' => 'application/json;charset=UTF-8;',
    ];
    $result = $gateway->notify($headers, $raw, [], []);

    assertTrue($result->status === 'paid', 'Expected completed PayPay webhook to be paid.');
    assertTrue($result->outTradeNo === 'TP202606260000001234ABCD', 'Expected merchant_order_id as local order number.');
    assertTrue($result->platformTradeNo === 'PAYPAY-ORDER-1', 'Expected order_id as platform trade number.');
    assertTrue($result->amount === 500, 'Expected order_amount to be parsed as integer amount.');
    assertTrue($result->currency === 'JPY', 'Expected order_amount currency to default to JPY.');
    assertTrue($result->signatureOk === true, 'Expected PayPay webhook signature to verify.');

    $nonTransaction = [
        'notification_type' => 'file.created',
        'notification_id' => 'notify-2',
    ];
    $raw = json_encode($nonTransaction, JSON_UNESCAPED_SLASHES);
    $headers['authorization'] = paypayAuth($config, $raw);
    $ignored = $gateway->notify($headers, $raw, [], []);

    assertTrue($ignored->status === 'ignored', 'Expected non-transaction PayPay webhook to be ignored.');
    assertTrue($ignored->outTradeNo === 'unknown', 'Expected non-transaction webhook to avoid missing-order exceptions.');

    $badMerchant = $webhook;
    $badMerchant['merchant_id'] = 'other-merchant';
    $raw = json_encode($badMerchant, JSON_UNESCAPED_SLASHES);
    $headers['authorization'] = paypayAuth($config, $raw);
    try {
        $gateway->notify($headers, $raw, [], []);
        fwrite(STDERR, "Expected merchant id mismatch to throw\n");
        exit(1);
    } catch (\RuntimeException $e) {
        assertTrue($e->getMessage() === 'PayPay merchant id mismatch.', 'Expected merchant id mismatch error.');
    }

    echo "PayPayWebhookTest passed\n";
}
