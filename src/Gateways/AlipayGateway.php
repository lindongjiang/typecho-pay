<?php

namespace TypechoPlugin\TypechoPay\Gateways;

use TypechoPlugin\TypechoPay\Contracts\GatewayInterface;
use TypechoPlugin\TypechoPay\Contracts\NotifyResult;
use TypechoPlugin\TypechoPay\Contracts\PayCreateResult;
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
        if ($this->config['alipayMode'] === 'precreate') {
            AlipaySdk::ensureAop(['AlipayTradePrecreateRequest']);

            $request = new \AlipayTradePrecreateRequest();
            $request->setNotifyUrl($this->notifyUrl('alipay'));
            $request->setBizContent(json_encode([
                'out_trade_no' => $order['out_trade_no'],
                'total_amount' => Money::cnyFenToYuan((int) $order['amount']),
                'subject' => $order['subject'],
            ], JSON_UNESCAPED_UNICODE));

            $response = $client->execute($request);
            $data = json_decode(json_encode($response), true);

            return new PayCreateResult(
                'qr',
                null,
                $data['alipay_trade_precreate_response']['qr_code'] ?? null,
                null,
                is_array($data) ? $data : []
            );
        }

        AlipaySdk::ensureAop(['AlipayTradePagePayRequest']);

        $request = new \AlipayTradePagePayRequest();
        $request->setNotifyUrl($this->notifyUrl('alipay'));
        $request->setReturnUrl(
            $this->returnUrl('alipay')
            . '&out_trade_no=' . rawurlencode($order['out_trade_no'])
            . '&poll_token=' . rawurlencode((string) ($order['poll_token'] ?? ''))
        );
        $request->setBizContent(json_encode([
            'out_trade_no' => $order['out_trade_no'],
            'product_code' => 'FAST_INSTANT_TRADE_PAY',
            'total_amount' => Money::cnyFenToYuan((int) $order['amount']),
            'subject' => $order['subject'],
        ], JSON_UNESCAPED_UNICODE));

        return new PayCreateResult('html', null, null, $client->pageExecute($request, 'POST'), []);
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
        $amount = isset($post['total_amount']) ? (int) round(((float) $post['total_amount']) * 100) : null;

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
        $amount = isset($result['total_amount']) ? (int) round(((float) $result['total_amount']) * 100) : null;

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

    private function aopClient()
    {
        AlipaySdk::ensureAop();
        $client = new \AopClient();
        $client->gatewayUrl = 'https://openapi.alipay.com/gateway.do';
        $client->appId = $this->config['alipayAppId'];
        $client->rsaPrivateKey = $this->config['alipayPrivateKey'];
        $client->alipayrsaPublicKey = $this->config['alipayPublicKey'];
        $client->signType = 'RSA2';
        $client->format = 'json';
        $client->charset = 'UTF-8';

        return $client;
    }
}
