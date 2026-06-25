<?php

namespace TypechoPlugin\TypechoPay\Services;

use Typecho\Db;
use TypechoPlugin\TypechoPay\Contracts\NotifyResult;
use TypechoPlugin\TypechoPay\Contracts\PayCreateResult;
use TypechoPlugin\TypechoPay\Support\Money;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

final class OrderService
{
    private const PAYABLE_STATUSES = ['pending', 'processing'];

    private Db $db;

    public function __construct(Db $db)
    {
        $this->db = $db;
    }

    public function create(array $input, ?int $userId = null, ?string $guestTokenHash = null): array
    {
        $existing = $this->findReusablePending($input, $userId, $guestTokenHash);
        if ($existing) {
            return $existing + ['reused' => true];
        }

        return $this->createFresh($input, $userId, $guestTokenHash) + ['reused' => false];
    }

    private function createFresh(array $input, ?int $userId, ?string $guestTokenHash): array
    {
        $now = date('Y-m-d H:i:s');
        $amount = Money::assertAmount($input['amount'] ?? 0);
        $currency = Money::assertCurrency($input['currency'] ?? 'CNY');
        $subject = trim((string) ($input['subject'] ?? 'TypechoPay Order'));
        $subjectLength = function_exists('mb_strlen') ? mb_strlen($subject) : strlen($subject);
        if ($subject === '' || $subjectLength > 255) {
            throw new \InvalidArgumentException('Invalid payment subject.');
        }

        $order = [
            'out_trade_no' => $this->makeTradeNo(),
            'gateway' => (string) $input['gateway'],
            'subject' => $subject,
            'amount' => $amount,
            'currency' => $currency,
            'biz_type' => trim((string) ($input['biz_type'] ?? 'post')) ?: 'post',
            'biz_id' => isset($input['biz_id']) ? (int) $input['biz_id'] : null,
            'user_id' => $userId,
            'guest_token_hash' => $guestTokenHash,
            'status' => 'pending',
            'platform_trade_no' => null,
            'pay_url' => null,
            'qr_content' => null,
            'paid_at' => null,
            'expired_at' => date('Y-m-d H:i:s', time() + 1800),
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $id = $this->db->query($this->db->insert('table.pay_orders')->rows($order));
        $order['id'] = $id;

        return $order;
    }

    public function findReusablePending(array $input, ?int $userId, ?string $guestTokenHash): ?array
    {
        $amount = Money::assertAmount($input['amount'] ?? 0);
        $currency = Money::assertCurrency($input['currency'] ?? 'CNY');
        $bizType = trim((string) ($input['biz_type'] ?? 'post')) ?: 'post';
        $bizId = isset($input['biz_id']) ? (int) $input['biz_id'] : null;
        $gateway = (string) ($input['gateway'] ?? '');

        $select = $this->db->select()->from('table.pay_orders')
            ->where('gateway = ?', $gateway)
            ->where('biz_type = ?', $bizType)
            ->where('amount = ?', $amount)
            ->where('currency = ?', $currency)
            ->where('status = ?', 'pending')
            ->where('expired_at > ?', date('Y-m-d H:i:s'))
            ->order('created_at', Db::SORT_DESC)
            ->limit(1);

        if ($bizId !== null) {
            $select->where('biz_id = ?', $bizId);
        }

        if ($userId !== null) {
            $select->where('user_id = ?', $userId);
        } elseif ($guestTokenHash !== null) {
            $select->where('guest_token_hash = ?', $guestTokenHash);
        } else {
            return null;
        }

        return $this->db->fetchRow($select);
    }

    public function attachCreateResult(string $outTradeNo, PayCreateResult $result): void
    {
        $this->db->query($this->db->update('table.pay_orders')->rows([
            'pay_url' => $result->payUrl,
            'qr_content' => $result->qrContent,
            'updated_at' => date('Y-m-d H:i:s'),
        ])->where('out_trade_no = ?', $outTradeNo));
    }

    public function findByOutTradeNo(string $outTradeNo): ?array
    {
        if (!$this->isValidTradeNo($outTradeNo)) {
            return null;
        }

        return $this->db->fetchRow(
            $this->db->select()->from('table.pay_orders')->where('out_trade_no = ?', $outTradeNo)->limit(1)
        );
    }

    public function markPaid(NotifyResult $result): array
    {
        $order = $this->findByOutTradeNo($result->outTradeNo);
        if (!$order) {
            throw new \RuntimeException('Order not found.');
        }

        if ($result->amount !== null && (int) $order['amount'] !== $result->amount) {
            throw new \RuntimeException('Payment amount mismatch.');
        }

        if ($result->currency !== null && strtoupper((string) $order['currency']) !== strtoupper($result->currency)) {
            throw new \RuntimeException('Payment currency mismatch.');
        }

        if ($order['status'] === 'paid') {
            return $order;
        }

        if (!in_array($order['status'], self::PAYABLE_STATUSES, true)) {
            throw new \RuntimeException('Order status is not payable.');
        }

        $updated = $this->db->query($this->db->update('table.pay_orders')->rows([
            'status' => 'paid',
            'platform_trade_no' => $result->platformTradeNo,
            'paid_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ])->where('out_trade_no = ?', $result->outTradeNo)
            ->where('status IN ?', self::PAYABLE_STATUSES));

        $paidOrder = $updated > 0 ? ($this->findByOutTradeNo($result->outTradeNo) ?: $order) : $order;
        if ($updated > 0) {
            (new AccessService($this->db))->grant($paidOrder);
        }

        return $paidOrder;
    }

    public function markFailed(string $outTradeNo, string $reason): void
    {
        if (!$this->isValidTradeNo($outTradeNo)) {
            return;
        }

        $this->db->query($this->db->update('table.pay_orders')->rows([
            'status' => 'failed',
            'updated_at' => date('Y-m-d H:i:s'),
        ])->where('out_trade_no = ?', $outTradeNo)->where('status = ?', 'pending'));

        $this->recordEvent($outTradeNo, 'system', 'failed', false, ['reason' => $reason]);
    }

    public function recordEvent(
        string $outTradeNo,
        string $gateway,
        string $eventType,
        bool $signatureOk,
        array $payload,
        array $context = []
    ): void
    {
        try {
            $this->db->query($this->db->insert('table.pay_events')->rows([
                'out_trade_no' => $outTradeNo !== '' ? $outTradeNo : 'unknown',
                'gateway' => $gateway,
                'event_type' => $eventType,
                'provider_event_id' => $context['provider_event_id'] ?? null,
                'provider_event_type' => $context['provider_event_type'] ?? null,
                'platform_trade_no' => $context['platform_trade_no'] ?? null,
                'remote_ip' => $context['remote_ip'] ?? null,
                'headers_json' => isset($context['headers']) ? json_encode($context['headers'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                'signature_ok' => $signatureOk ? 1 : 0,
                'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'created_at' => date('Y-m-d H:i:s'),
            ]));
        } catch (\Throwable $e) {
            error_log('[TypechoPay] Failed to record event: ' . $e->getMessage());
        }
    }

    public function publicOrderStatus(?array $order): array
    {
        if (!$order) {
            return ['success' => false, 'error' => 'Order not found.'];
        }

        return [
            'success' => true,
            'data' => [
                'out_trade_no' => $order['out_trade_no'],
                'gateway' => $order['gateway'],
                'status' => $order['status'],
                'amount' => (int) $order['amount'],
                'currency' => $order['currency'],
                'paid_at' => $order['paid_at'],
            ],
        ];
    }

    private function makeTradeNo(): string
    {
        return 'TP' . date('YmdHis') . strtoupper(bin2hex(random_bytes(4)));
    }

    private function isValidTradeNo(string $outTradeNo): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9_-]{8,64}$/', $outTradeNo);
    }
}
