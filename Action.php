<?php

namespace TypechoPlugin\TypechoPay;

use Typecho\Db;
use Typecho\Common;
use TypechoPlugin\TypechoPay\Gateways\GatewayFactory;
use TypechoPlugin\TypechoPay\Services\NonceService;
use TypechoPlugin\TypechoPay\Services\OrderService;
use TypechoPlugin\TypechoPay\Support\GuestToken;
use TypechoPlugin\TypechoPay\Support\HttpHeaders;
use TypechoPlugin\TypechoPay\Support\Signer;
use Widget\ActionInterface;
use Widget\Base\Options as BaseOptions;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class Action extends BaseOptions implements ActionInterface
{
    public function action()
    {
        $do = (string) $this->request->get('do');

        try {
            if ($do === 'create') {
                $this->create();
                return;
            }

            if ($do === 'notify') {
                $this->notify();
                return;
            }

            if ($do === 'query') {
                $this->query();
                return;
            }

            if ($do === 'return') {
                $this->paymentReturn();
                return;
            }

            $this->json(['success' => false, 'error' => 'Unknown action.'], 404);
        } catch (\InvalidArgumentException $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            error_log('[TypechoPay] ' . $e->getMessage());
            $this->json(['success' => false, 'error' => 'Payment service is unavailable.'], 500);
        }
    }

    private function create(): void
    {
        if (!$this->request->isPost()) {
            throw new \InvalidArgumentException('Payment order must be created by POST.');
        }

        $config = Plugin::pluginConfig($this->options);
        $gateway = strtolower((string) $this->request->get('gateway'));
        if (!in_array($gateway, $config['enabledGateways'], true)) {
            throw new \InvalidArgumentException('Payment gateway is disabled.');
        }

        $payload = [
            'gateway' => $gateway,
            'amount' => (string) $this->request->get('amount'),
            'currency' => strtoupper((string) $this->request->get('currency')),
            'subject' => (string) $this->request->get('subject'),
            'biz_type' => (string) $this->request->get('biz_type'),
            'biz_id' => (string) $this->request->get('biz_id'),
            'ts' => (string) $this->request->get('ts'),
            'nonce' => (string) $this->request->get('nonce'),
        ];

        $this->assertFreshPayload($payload);
        if (!Signer::verify($payload, Plugin::signingSecret($this->options, $config), (string) $this->request->get('signature'))) {
            throw new \InvalidArgumentException('Invalid payment entry signature.');
        }
        (new NonceService(Db::get()))->consume('create', $payload['nonce']);

        $this->assertGatewayCurrency($gateway, $payload['currency']);

        $orderService = new OrderService(Db::get());
        $userId = $this->user->hasLogin() ? (int) $this->user->uid : null;
        $guestTokenHash = $userId === null ? GuestToken::hash(GuestToken::getOrCreate()) : null;
        $order = $orderService->create($payload + ['gateway' => $gateway], $userId, $guestTokenHash);

        if (!empty($order['reused']) && ($order['pay_url'] || $order['qr_content'])) {
            $this->renderPayment($order, $this->createResultFromOrder($order));
            return;
        }

        try {
            $adapter = GatewayFactory::make($gateway, $config, $this->options);
            $result = $adapter->create($order);
            $orderService->attachCreateResult($order['out_trade_no'], $result);
            $this->renderPayment($order, $result);
        } catch (\Throwable $e) {
            $orderService->markFailed($order['out_trade_no'], $e->getMessage());
            throw $e;
        }
    }

    private function notify(): void
    {
        $config = Plugin::pluginConfig($this->options);
        $gateway = strtolower((string) $this->request->get('gateway'));
        if (!in_array($gateway, ['paypay', 'wechat', 'alipay'], true)) {
            $this->providerResponse($gateway, false);
            return;
        }

        $rawBody = file_get_contents('php://input') ?: '';
        $post = $_POST;
        $headers = HttpHeaders::fromServer();
        $orderService = new OrderService(Db::get());

        try {
            $result = GatewayFactory::make($gateway, $config, $this->options)
                ->notify($headers, $rawBody, $_GET, $post);
            $orderService->recordEvent($result->outTradeNo, $gateway, $result->status, $result->signatureOk, $result->raw, [
                'provider_event_id' => $result->providerEventId,
                'provider_event_type' => $result->providerEventType,
                'platform_trade_no' => $result->platformTradeNo,
                'remote_ip' => $_SERVER['REMOTE_ADDR'] ?? null,
                'headers' => $headers,
            ]);

            if ($result->isPaid()) {
                $orderService->markPaid($result);
            }

            $this->providerResponse($gateway, $result->signatureOk);
        } catch (\Throwable $e) {
            $orderService->recordEvent('unknown', $gateway, 'notify_error', false, ['error' => $e->getMessage()]);
            $this->providerResponse($gateway, false);
        }
    }

    private function query(): void
    {
        $outTradeNo = (string) $this->request->get('out_trade_no');
        $config = Plugin::pluginConfig($this->options);
        $orderService = new OrderService(Db::get());
        $order = $orderService->findByOutTradeNo($outTradeNo);
        if (!$order) {
            $this->json(['success' => false, 'error' => 'Order not found.'], 404);
            return;
        }

        if (in_array($order['status'], ['pending', 'processing'], true)) {
            try {
                $result = GatewayFactory::make($order['gateway'], $config, $this->options)->query($order);
                $orderService->recordEvent($result->outTradeNo, $order['gateway'], 'active_query:' . $result->status, $result->signatureOk, $result->raw, [
                    'provider_event_id' => $result->providerEventId,
                    'provider_event_type' => $result->providerEventType,
                    'platform_trade_no' => $result->platformTradeNo,
                    'remote_ip' => $_SERVER['REMOTE_ADDR'] ?? null,
                ]);

                if ($result->isPaid()) {
                    $order = $orderService->markPaid($result);
                }
            } catch (\Throwable $e) {
                error_log('[TypechoPay] Active query failed: ' . $e->getMessage());
            }
        }

        $this->json($orderService->publicOrderStatus($orderService->findByOutTradeNo($outTradeNo) ?: $order));
    }

    private function paymentReturn(): void
    {
        $outTradeNo = htmlspecialchars((string) $this->request->get('out_trade_no'));
        $this->response->throwContent(
            '<!doctype html><meta charset="utf-8"><title>Payment Return</title>'
            . '<p>支付完成后订单状态会通过异步通知更新。</p>'
            . ($outTradeNo !== '' ? '<p>订单号：' . $outTradeNo . '</p>' : ''),
            'text/html'
        );
    }

    private function renderPayment(array $order, $result): void
    {
        if ($result->type === 'html' && $result->html !== null) {
            $this->response->throwContent($result->html, 'text/html');
            return;
        }

        if ($result->type === 'redirect' && $result->payUrl !== null) {
            $this->response->redirect($result->payUrl);
            return;
        }

        $pollUrl = Common::url('/action/typechopay?do=query&out_trade_no=' . rawurlencode($order['out_trade_no']), $this->options->index);
        $payUrl = $result->payUrl;
        $qrContent = $result->qrContent ?: $payUrl;
        $html = '<!doctype html><html><head><meta charset="utf-8"><title>支付订单</title>'
            . '<style>body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;margin:40px;line-height:1.6}'
            . '.box{max-width:640px}.code{word-break:break-all;padding:12px;background:#f6f7f8;border:1px solid #ddd}'
            . '#typechopay-qrcode{width:240px;height:240px;margin:16px 0}.status{color:#555}</style>'
            . '</head><body><main class="box">'
            . '<h1>支付订单</h1>'
            . '<p>订单号：' . htmlspecialchars($order['out_trade_no']) . '</p>'
            . '<p>金额：' . htmlspecialchars($order['currency'] . ' ' . $order['amount']) . '</p>';

        if ($result->type === 'qr' && $qrContent) {
            $html .= '<canvas id="typechopay-qrcode" width="240" height="240" data-text="' . htmlspecialchars($qrContent) . '"></canvas>'
                . ($payUrl ? '<p><a href="' . htmlspecialchars($payUrl) . '" rel="nofollow">打开支付 App</a></p>' : '')
                . '<p class="code">' . htmlspecialchars($qrContent) . '</p>'
                . '<p class="status" id="typechopay-status">等待支付...</p>'
                . '<script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>'
                . '<script>(function(){var box=document.getElementById("typechopay-qrcode");'
                . 'if(window.QRCode&&box){QRCode.toCanvas(box,box.getAttribute("data-text"),{width:240,margin:1});}'
                . 'var status=document.getElementById("typechopay-status");'
                . 'setInterval(function(){fetch(' . json_encode($pollUrl) . ',{credentials:"same-origin"}).then(function(r){return r.json();}).then(function(j){if(j&&j.data){status.textContent="订单状态："+j.data.status;if(j.data.status==="paid"){location.reload();}}}).catch(function(){});},3000);'
                . '}());</script>';
        } elseif ($payUrl) {
            $html .= '<p><a href="' . htmlspecialchars($payUrl) . '" rel="nofollow">打开支付链接</a></p>';
        } else {
            $html .= '<p>支付网关未返回可展示的支付入口。</p>';
        }

        $html .= '</main></body></html>';
        $this->response->throwContent($html, 'text/html');
    }

    private function providerResponse(string $gateway, bool $success): void
    {
        if ($gateway === 'alipay') {
            $this->response->throwContent($success ? 'success' : 'failure', 'text/plain');
            return;
        }

        if ($gateway === 'wechat') {
            $this->response->setStatus($success ? 200 : 400);
            $this->response->throwJson($success
                ? ['code' => 'SUCCESS', 'message' => '成功']
                : ['code' => 'FAIL', 'message' => '失败']);
            return;
        }

        $this->response->setStatus($success ? 200 : 400);
        $this->response->throwContent($success ? 'OK' : 'FAIL', 'text/plain');
    }

    private function json(array $payload, int $status = 200): void
    {
        $this->response->setStatus($status);
        $this->response->throwJson($payload);
    }

    private function assertGatewayCurrency(string $gateway, string $currency): void
    {
        $expected = $gateway === 'paypay' ? 'JPY' : 'CNY';
        if ($currency !== $expected) {
            throw new \InvalidArgumentException('Currency does not match payment gateway.');
        }
    }

    private function assertFreshPayload(array $payload): void
    {
        $ts = filter_var($payload['ts'] ?? null, FILTER_VALIDATE_INT);
        if ($ts === false || abs(time() - (int) $ts) > 600) {
            throw new \InvalidArgumentException('Payment payload expired.');
        }

        if (!preg_match('/^[a-f0-9]{16}$/', (string) ($payload['nonce'] ?? ''))) {
            throw new \InvalidArgumentException('Invalid payment payload nonce.');
        }
    }

    private function createResultFromOrder(array $order)
    {
        return new Contracts\PayCreateResult(
            $order['qr_content'] ? 'qr' : 'redirect',
            $order['pay_url'] ?: null,
            $order['qr_content'] ?: null
        );
    }
}
