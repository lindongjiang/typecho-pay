<?php

namespace TypechoPlugin\TypechoPay\Gateways;

use TypechoPlugin\TypechoPay\Contracts\GatewayInterface;
use TypechoPlugin\TypechoPay\Contracts\NotifyResult;
use TypechoPlugin\TypechoPay\Contracts\PayCreateResult;
use TypechoPlugin\TypechoPay\Support\AlipayKey;
use TypechoPlugin\TypechoPay\Support\AlipaySdk;
use TypechoPlugin\TypechoPay\Support\Money;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

final class AlipayGateway extends AbstractGateway implements GatewayInterface
{
    public function create(array $order): PayCreateResult
    {
        $this->requireConfig(['alipayAppId', 'alipayPrivateKey', 'alipayPublicKey']);
        if (strtoupper((string) $order['currency']) !== 'CNY') {
            throw new \InvalidArgumentException('Alipay orders must use CNY.');
        }

        $client = $this->aopClient();

        // Mobile browsers use Alipay Wap Pay (H5); desktop browsers use Page Pay.
        if ($this->isMobileBrowser()) {
            return $this->createWapPay($client, $order);
        }

        return $this->createPagePay($client, $order);
    }

    public function notify(array $headers, string $rawBody, array $query, array $post): NotifyResult
    {
        $this->requireConfig(['alipayAppId', 'alipayPrivateKey', 'alipayPublicKey']);
        AlipaySdk::ensureAop();
        $signatureOk = $this->aopClient()->rsaCheckV1($post, null, 'RSA2');
        $tradeStatus = (string) ($post['trade_status'] ?? '');
        $status = in_array($tradeStatus, ['TRADE_SUCCESS', 'TRADE_FINISHED'], true)
            ? 'paid'
            : strtolower($tradeStatus ?: 'ignored');
        $amount = isset($post['total_amount']) ? Money::yuanStringToFen((string) $post['total_amount']) : null;

        if (($post['app_id'] ?? '') !== $this->config['alipayAppId']) {
            $signatureOk = false;
        }

        if ($this->config['alipaySellerId'] !== '' && ($post['seller_id'] ?? '') !== $this->config['alipaySellerId']) {
            $signatureOk = false;
        }

        return new NotifyResult(
            $signatureOk ? $status : 'invalid_signature',
            (string) ($post['out_trade_no'] ?? ''),
            isset($post['trade_no']) ? (string) $post['trade_no'] : null,
            $amount,
            'CNY',
            $signatureOk,
            $post,
            isset($post['notify_id']) ? (string) $post['notify_id'] : null,
            isset($post['notify_type']) ? (string) $post['notify_type'] : null
        );
    }

    public function query(array $order): NotifyResult
    {
        $this->requireConfig(['alipayAppId', 'alipayPrivateKey', 'alipayPublicKey']);
        AlipaySdk::ensureAop(['AlipayTradeQueryRequest']);

        $request = new \AlipayTradeQueryRequest();
        $request->setBizContent(json_encode([
            'out_trade_no' => $order['out_trade_no'],
        ], JSON_UNESCAPED_UNICODE));

        $response = $this->aopClient()->execute($request);
        $data = json_decode(json_encode($response), true);
        $result = is_array($data) ? ($data['alipay_trade_query_response'] ?? $data) : [];
        $tradeStatus = (string) ($result['trade_status'] ?? '');
        $amount = isset($result['total_amount']) ? Money::yuanStringToFen((string) $result['total_amount']) : null;

        return new NotifyResult(
            in_array($tradeStatus, ['TRADE_SUCCESS', 'TRADE_FINISHED'], true) ? 'paid' : strtolower($tradeStatus ?: 'pending'),
            (string) ($result['out_trade_no'] ?? $order['out_trade_no']),
            isset($result['trade_no']) ? (string) $result['trade_no'] : null,
            $amount,
            'CNY',
            true,
            is_array($data) ? $data : [],
            null,
            'active_query'
        );
    }

    private function createPagePay($client, array $order): PayCreateResult
    {
        AlipaySdk::ensureAop(['AlipayTradePagePayRequest']);

        $request = new \AlipayTradePagePayRequest();
        $request->setNotifyUrl($this->notifyUrl('alipay'));
        $request->setReturnUrl(
            $this->returnUrl('alipay')
            . '&out_trade_no=' . rawurlencode($order['out_trade_no'])
            . '&return_token=' . rawurlencode((string) ($order['return_token'] ?? ''))
        );
        $request->setBizContent(json_encode([
            'out_trade_no' => $order['out_trade_no'],
            'product_code' => 'FAST_INSTANT_TRADE_PAY',
            'total_amount' => Money::cnyFenToYuan((int) $order['amount']),
            'subject' => $order['subject'],
        ], JSON_UNESCAPED_UNICODE));

        return new PayCreateResult('html', null, null, $client->pageExecute($request, 'POST'), []);
    }

    /**
     * Mobile: H5 Wap Pay — redirect to Alipay mobile page.
     */
    private function createWapPay($client, array $order): PayCreateResult
    {
        AlipaySdk::ensureAop(['AlipayTradeWapPayRequest']);

        $request = new \AlipayTradeWapPayRequest();
        $request->setNotifyUrl($this->notifyUrl('alipay'));
        $request->setReturnUrl(
            $this->returnUrl('alipay')
            . '&out_trade_no=' . rawurlencode($order['out_trade_no'])
            . '&return_token=' . rawurlencode((string) ($order['return_token'] ?? ''))
        );
        $request->setBizContent(json_encode([
            'out_trade_no' => $order['out_trade_no'],
            'total_amount' => Money::cnyFenToYuan((int) $order['amount']),
            'subject' => $order['subject'],
            'product_code' => 'QUICK_WAP_WAY',
        ], JSON_UNESCAPED_UNICODE));

        $payUrl = $client->pageExecute($request, 'GET');

        return new PayCreateResult(
            'redirect',
            is_string($payUrl) ? $payUrl : null,
            null,
            null,
            []
        );
    }

    private function isMobileBrowser(): bool
    {
        $ua = strtolower((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
        if ($ua === '') {
            return false;
        }

        $mobileKeywords = [
            'mobile', 'android', 'iphone', 'ipod', 'ipad', 'windows phone',
            'blackberry', 'opera mini', 'opera mobi', 'webos', 'ucbrowser',
            'micromessenger', 'alipayclient', 'wechat',
        ];

        foreach ($mobileKeywords as $keyword) {
            if (strpos($ua, $keyword) !== false) {
                return true;
            }
        }

        return false;
    }

    private function aopClient()
    {
        AlipaySdk::ensureAop();
        $privateKeyBody = AlipayKey::body((string) ($this->config['alipayPrivateKey'] ?? ''));
        $publicKeyBody = AlipayKey::body((string) ($this->config['alipayPublicKey'] ?? ''));
        if ($privateKeyBody === '' || $publicKeyBody === '') {
            throw new \InvalidArgumentException('Invalid Alipay key configuration.');
        }

        $client = new \AopClient();
        $client->gatewayUrl = $this->gatewayUrl();
        $client->appId = $this->config['alipayAppId'];
        $client->rsaPrivateKey = $privateKeyBody;
        $client->alipayrsaPublicKey = $publicKeyBody;
        $client->apiVersion = '1.0';
        $client->signType = 'RSA2';
        $client->format = 'json';
        $client->postCharset = 'UTF-8';

        return $client;
    }

    private function gatewayUrl(): string
    {
        $url = trim((string) ($this->config['alipayGatewayUrl'] ?? ''));
        if ($url === '') {
            return 'https://openapi.alipay.com/gateway.do';
        }

        $parts = parse_url($url);
        if (!is_array($parts)
            || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || empty($parts['host'])
            || ($parts['path'] ?? '') === '') {
            return 'https://openapi.alipay.com/gateway.do';
        }

        return $url;
    }
}
