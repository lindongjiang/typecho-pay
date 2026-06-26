<?php

namespace TypechoPlugin\TypechoPay;

use Typecho\Db;
use Typecho\Common;
use TypechoPlugin\TypechoPay\Gateways\GatewayFactory;
use TypechoPlugin\TypechoPay\Services\GuestClaimService;
use TypechoPlugin\TypechoPay\Services\NonceService;
use TypechoPlugin\TypechoPay\Services\OrderService;
use TypechoPlugin\TypechoPay\Services\ProductService;
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
            if ($do === 'prepare') {
                $this->prepare();
                return;
            }

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

            if ($do === 'grant') {
                $this->grant();
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
        $payload = $this->entryPayloadFromRequest() + [
            'ts' => (string) $this->request->get('ts'),
            'nonce' => (string) $this->request->get('nonce'),
        ];

        $this->assertFreshPayload($payload);
        if (!Signer::verify($payload, Plugin::signingSecret($this->options, $config), (string) $this->request->get('signature'))) {
            throw new \InvalidArgumentException('Invalid payment entry signature.');
        }
        (new NonceService(Db::get()))->consume('create', $payload['nonce']);

        $this->createFromPayload($payload, $config);
    }

    private function prepare(): void
    {
        if (!$this->request->isPost()) {
            throw new \InvalidArgumentException('Payment entry must be prepared by POST.');
        }

        $config = Plugin::pluginConfig($this->options);
        $payload = $this->entryPayloadFromRequest();

        if (!Signer::verify($payload, Plugin::signingSecret($this->options, $config), (string) $this->request->get('entry_signature'))) {
            throw new \InvalidArgumentException('Invalid payment entry signature.');
        }

        $this->createFromPayload($payload, $config);
    }

    private function createFromPayload(array $payload, array $config): void
    {
        $gateway = strtolower((string) ($payload['gateway'] ?? ''));
        if (!in_array($gateway, $config['enabledGateways'], true)) {
            throw new \InvalidArgumentException('Payment gateway is disabled.');
        }

        $payload['return_to'] = $this->safeReturnTo($payload['return_to']);
        $product = (new ProductService(Db::get()))->resolve($payload);
        $this->assertGatewayCurrency($gateway, (string) $product['currency']);
        $this->assertBizTarget((string) $product['biz_type'], (string) $product['biz_id']);

        $orderInput = array_merge($payload, [
            'amount' => (int) $product['amount'],
            'currency' => (string) $product['currency'],
            'subject' => (string) $product['subject'],
            'biz_type' => (string) $product['biz_type'],
            'biz_id' => (int) $product['biz_id'],
            'product_id' => $product['product_id'],
            'product_key' => $product['product_key'],
            'product_version' => (int) $product['product_version'],
            'product_snapshot_json' => (string) $product['product_snapshot_json'],
            'purchase_policy' => (string) $product['purchase_policy'],
        ]);

        $orderService = new OrderService(Db::get());
        $accessService = new Services\AccessService(Db::get());
        $userId = $this->user->hasLogin() ? (int) $this->user->uid : null;
        $guestToken = $userId === null ? GuestToken::getOrCreate() : GuestToken::get();
        $guestTokenHash = GuestToken::hash($guestToken);
        if ($userId !== null) {
            (new GuestClaimService(Db::get()))->claimAll($userId, $guestTokenHash);
        }

        if (($product['purchase_policy'] ?? 'once') === 'once'
            && $accessService->canAccess((string) $product['biz_type'], (int) $product['biz_id'], $userId, $guestTokenHash)) {
            throw new \InvalidArgumentException('Content is already purchased.');
        }

        $order = $orderService->create($orderInput + ['gateway' => $gateway], $userId, $guestTokenHash);

        try {
            $adapter = GatewayFactory::make($gateway, $config, $this->options);
            $orderService->markProcessing($order['out_trade_no']);
            $result = $adapter->create($order);
        } catch (\Throwable $e) {
            if ($this->isDefiniteCreateFailure($e)) {
                $orderService->markFailed($order['out_trade_no'], $e->getMessage());
            } else {
                $orderService->markCreateUnknown($order['out_trade_no'], $e->getMessage());
            }
            throw $e;
        }

        $orderService->attachCreateResult($order['out_trade_no'], $result);
        $this->renderPayment($order, $result);
    }

    private function entryPayloadFromRequest(): array
    {
        $payload = [
            'gateway' => strtolower((string) $this->request->get('gateway')),
            'return_to' => (string) $this->request->get('return_to'),
        ];

        foreach (['product_id', 'product', 'amount', 'currency', 'subject', 'biz_type', 'biz_id', 'purchase_policy'] as $key) {
            $value = $this->request->get($key);
            if ($value !== null && (string) $value !== '') {
                $payload[$key] = $key === 'currency' ? strtoupper((string) $value) : (string) $value;
            }
        }

        return $payload;
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
            } else {
                $orderService->syncProviderStatus($result);
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

        if (!$this->canReadOrder($orderService, $order, (string) $this->request->get('poll_token'))) {
            $this->json(['success' => false, 'error' => 'Order access denied.'], 403);
            return;
        }

        if (in_array($order['status'], ['pending', 'processing'], true)) {
            try {
                $order = $this->activeQuery($orderService, $order, $config, false);
            } catch (\Throwable $e) {
                error_log('[TypechoPay] Active query failed: ' . $e->getMessage());
            }
        }

        $this->json($orderService->publicOrderStatus($orderService->findByOutTradeNo($outTradeNo) ?: $order));
    }

    private function paymentReturn(): void
    {
        $outTradeNo = (string) $this->request->get('out_trade_no');
        $config = Plugin::pluginConfig($this->options);
        $orderService = new OrderService(Db::get());
        $order = $orderService->findByOutTradeNo($outTradeNo);
        if ($order && !$this->canReadOrder($orderService, $order, (string) $this->request->get('poll_token'))) {
            $this->response->setStatus(403);
            $this->response->throwContent(
                '<!doctype html><meta charset="utf-8"><title>Payment Return</title><p>订单访问凭证无效。</p>',
                'text/html'
            );
            return;
        }

        if ($order && in_array($order['status'], ['pending', 'processing'], true)) {
            try {
                $order = $this->activeQuery($orderService, $order, $config, false);
            } catch (\Throwable $e) {
                error_log('[TypechoPay] Return active query failed: ' . $e->getMessage());
            }
        }

        $returnTo = $this->safeReturnTo((string) ($order['return_to'] ?? $this->request->get('return_to')));
        $fulfillmentStatus = (string) ($order['fulfillment_status'] ?? '');
        if ($order && $order['status'] === 'paid' && !in_array($fulfillmentStatus, ['failed', 'partial'], true)) {
            $this->response->redirect($returnTo);
            return;
        }

        $safeOutTradeNo = htmlspecialchars($outTradeNo);
        $safeReturnTo = htmlspecialchars($returnTo);
        $status = htmlspecialchars((string) ($order['status'] ?? 'unknown'));
        $fulfillment = htmlspecialchars($fulfillmentStatus);
        $this->response->throwContent(
            '<!doctype html><meta charset="utf-8"><title>Payment Return</title>'
            . '<p>订单状态：' . $status . '</p>'
            . ($fulfillment !== '' ? '<p>交付状态：' . $fulfillment . '</p>' : '')
            . ($safeOutTradeNo !== '' ? '<p>订单号：' . $safeOutTradeNo . '</p>' : '')
            . '<p><a href="' . $safeReturnTo . '">返回查看内容</a></p>',
            'text/html'
        );
    }

    private function grant(): void
    {
        $this->user->pass('administrator');
        $this->security->protect();
        if (!$this->request->isPost()) {
            throw new \InvalidArgumentException('Grant must be retried by POST.');
        }

        $outTradeNo = (string) $this->request->get('out_trade_no');
        (new OrderService(Db::get()))->regrant($outTradeNo);

        $this->response->redirect($this->request->getReferer() ?: (string) $this->options->adminUrl);
    }

    private function renderPayment(array $order, $result): void
    {
        $this->setNoStoreHeaders();

        if ($result->type === 'html' && $result->html !== null) {
            $this->response->throwContent($result->html, 'text/html');
            return;
        }

        if ($result->type === 'redirect' && $result->payUrl !== null) {
            $this->response->redirect($result->payUrl);
            return;
        }

        $pollUrl = Common::url(
            '/action/typechopay?do=query&out_trade_no=' . rawurlencode($order['out_trade_no'])
            . '&poll_token=' . rawurlencode((string) ($order['poll_token'] ?? '')),
            $this->options->index
        );
        $returnTo = $this->safeReturnTo((string) ($order['return_to'] ?? ''));
        $payUrl = $result->payUrl;
        $qrContent = $result->qrContent ?: $payUrl;
        $html = '<!doctype html><html><head><meta charset="utf-8"><title>支付订单</title>'
            . '<style>body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;margin:40px;line-height:1.6}'
            . '.box{max-width:640px}.code{word-break:break-all;padding:12px;background:#f6f7f8;border:1px solid #ddd}'
            . '#typechopay-qrcode{width:240px;height:240px;margin:16px 0}.status{color:#555}</style>'
            . '</head><body><main class="box">'
            . '<h1>支付订单</h1>'
            . '<p>订单号：' . htmlspecialchars($order['out_trade_no']) . '</p>'
            . '<p>金额：' . htmlspecialchars(Support\Money::formatForDisplay((int) $order['amount'], (string) $order['currency'])) . '</p>';

        if ($result->type === 'qr' && $qrContent) {
            $html .= '<div id="typechopay-qrcode" data-text="' . htmlspecialchars($qrContent) . '"></div>'
                . ($payUrl ? '<p><a href="' . htmlspecialchars($payUrl) . '" rel="nofollow">打开支付 App</a></p>' : '')
                . '<p class="code">' . htmlspecialchars($qrContent) . '</p>'
                . '<p class="status" id="typechopay-status">等待支付...</p>'
                . '<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js" integrity="sha384-3zSEDfvllQohrq0PHL1fOXJuC/jSOO34H46t6UQfobFOmxE5BpjjaIJY5F2/bMnU" crossorigin="anonymous"></script>'
                . '<script>(function(){var box=document.getElementById("typechopay-qrcode");'
                . 'if(window.QRCode&&box){new QRCode(box,{text:box.getAttribute("data-text"),width:240,height:240,correctLevel:QRCode.CorrectLevel.M});}'
                . 'var status=document.getElementById("typechopay-status");'
                . 'var returnTo=' . json_encode($returnTo) . ';'
                . 'var timer=setInterval(function(){fetch(' . json_encode($pollUrl) . ',{credentials:"same-origin"}).then(function(r){return r.json();}).then(function(j){if(j&&j.data){status.textContent="订单状态："+j.data.status;if(j.data.status==="paid"&&j.data.fulfillment_status!=="failed"&&j.data.fulfillment_status!=="partial"){clearInterval(timer);location.href=returnTo;}else if(j.data.status==="grant_failed"||j.data.fulfillment_status==="failed"||j.data.fulfillment_status==="partial"){clearInterval(timer);status.textContent="支付成功，交付未完全完成，请联系站点管理员。";}else if(j.data.terminal){clearInterval(timer);status.textContent="订单状态："+j.data.status+"，请重新发起支付。";}}}).catch(function(){});},3000);'
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
        $this->setNoStoreHeaders();
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

    private function activeQuery(OrderService $orderService, array $order, array $config, bool $force): array
    {
        if (!$orderService->shouldQueryUpstream($order, $force)) {
            return $order;
        }

        $order = $orderService->markQueryAttempt($order);
        $result = GatewayFactory::make($order['gateway'], $config, $this->options)->query($order);
        $orderService->recordEvent($result->outTradeNo, $order['gateway'], 'active_query:' . $result->status, $result->signatureOk, $result->raw, [
            'provider_event_id' => $result->providerEventId,
            'provider_event_type' => $result->providerEventType,
            'platform_trade_no' => $result->platformTradeNo,
            'remote_ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);

        if ($result->isPaid()) {
            return $orderService->markPaid($result);
        }

        $synced = $orderService->syncProviderStatus($result);
        if ($synced !== null) {
            return $synced;
        }

        return $orderService->findByOutTradeNo($order['out_trade_no']) ?: $order;
    }

    private function safeReturnTo(string $returnTo): string
    {
        $fallback = (string) $this->options->index;
        $returnTo = trim($returnTo);
        if ($returnTo === '' || preg_match('/[\r\n]/', $returnTo)) {
            return $fallback;
        }

        if (strpos($returnTo, '/') === 0 && strpos($returnTo, '//') !== 0) {
            return Common::url($returnTo, $this->options->index);
        }

        $target = parse_url($returnTo);
        $site = parse_url((string) $this->options->index);
        if (!$target || !$site || empty($target['scheme']) || empty($target['host']) || empty($site['host'])) {
            return $fallback;
        }

        if (!in_array(strtolower($target['scheme']), ['http', 'https'], true)) {
            return $fallback;
        }

        return strtolower($target['host']) === strtolower($site['host']) ? $returnTo : $fallback;
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

    private function assertBizTarget(string $bizType, string $bizId): void
    {
        if (!preg_match('/^[a-z0-9_.-]{1,32}$/', $bizType)) {
            throw new \InvalidArgumentException('Invalid business type.');
        }

        $id = filter_var($bizId, FILTER_VALIDATE_INT);
        if ($id === false || (int) $id <= 0) {
            throw new \InvalidArgumentException('Invalid business id.');
        }
    }

    private function canReadOrder(OrderService $orderService, array $order, string $pollToken): bool
    {
        if ($pollToken !== '' && $orderService->verifyPollToken($order, $pollToken)) {
            return true;
        }

        $userId = $this->user->hasLogin() ? (int) $this->user->uid : null;
        $guestTokenHash = GuestToken::hash(GuestToken::get());
        if ($userId !== null && $guestTokenHash !== null) {
            (new GuestClaimService(Db::get()))->claimAll($userId, $guestTokenHash);
        }

        return $orderService->belongsToOwner($order, $userId, $guestTokenHash);
    }

    private function isDefiniteCreateFailure(\Throwable $e): bool
    {
        if ($e instanceof \InvalidArgumentException) {
            return true;
        }

        $message = $e->getMessage();
        return strpos($message, 'Missing gateway config:') === 0
            || strpos($message, 'Install ') === 0
            || strpos($message, ' orders must use ') !== false;
    }

    private function setNoStoreHeaders(): void
    {
        $this->response->setHeader('Cache-Control', 'private, no-store, max-age=0');
        $this->response->setHeader('Pragma', 'no-cache');
        $this->response->setHeader('Expires', '0');
    }
}
