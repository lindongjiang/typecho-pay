<?php

namespace TypechoPlugin\TypechoPay\Services;

use Typecho\Db;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

final class AccessService
{
    private Db $db;

    public function __construct(Db $db)
    {
        $this->db = $db;
    }

    public function grant(array $order, ?array $deliverable = null): void
    {
        $bizType = $deliverable['biz_type'] ?? $deliverable['target_type'] ?? $order['biz_type'] ?? null;
        $bizId = $deliverable['biz_id'] ?? $deliverable['target_id'] ?? $order['biz_id'] ?? null;
        $deliverableId = isset($deliverable['id']) ? (int) $deliverable['id'] : 0;

        if (empty($order['id']) || empty($bizType) || empty($bizId)) {
            throw new \RuntimeException('Invalid entitlement target.');
        }

        if (empty($order['user_id']) && empty($order['guest_token_hash'])) {
            throw new \RuntimeException('Missing entitlement owner.');
        }

        if ($this->hasOrderGrant((int) $order['id'], $deliverableId)) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        try {
            $this->db->query($this->db->insert('table.pay_entitlements')->rows([
                'order_id' => (int) $order['id'],
                'deliverable_id' => $deliverableId,
                'biz_type' => (string) $bizType,
                'biz_id' => (int) $bizId,
                'user_id' => isset($order['user_id']) ? (int) $order['user_id'] : null,
                'guest_token_hash' => $order['guest_token_hash'] ?? null,
                'starts_at' => $now,
                'expires_at' => null,
                'created_at' => $now,
            ]));
        } catch (\Throwable $e) {
            if ($this->hasOrderGrant((int) $order['id'], $deliverableId)) {
                return;
            }

            throw $e;
        }
    }

    public function hasOrderGrant(int $orderId, int $deliverableId = 0): bool
    {
        if ($orderId <= 0) {
            return false;
        }

        return (bool) $this->db->fetchRow(
            $this->db->select('id')->from('table.pay_entitlements')
                ->where('order_id = ?', $orderId)
                ->where('deliverable_id = ?', $deliverableId)
                ->limit(1)
        );
    }

    public function canAccess(string $bizType, int $bizId, ?int $userId, ?string $guestTokenHash): bool
    {
        if ($bizId <= 0) {
            return false;
        }

        if ($userId === null && $guestTokenHash === null) {
            return false;
        }

        $now = date('Y-m-d H:i:s');
        $select = $this->db->select('id')->from('table.pay_entitlements')
            ->where('biz_type = ?', $bizType)
            ->where('biz_id = ?', $bizId)
            ->where('starts_at <= ?', $now)
            ->where('(expires_at IS NULL OR expires_at > ?)', $now)
            ->limit(1);

        if ($userId !== null && $guestTokenHash !== null) {
            $select->where('(user_id = ? OR guest_token_hash = ?)', $userId, $guestTokenHash);
        } elseif ($userId !== null) {
            $select->where('user_id = ?', $userId);
        } else {
            $select->where('guest_token_hash = ?', $guestTokenHash);
        }

        return (bool) $this->db->fetchRow($select);
    }

    public function claimGuestEntitlements(int $userId, ?string $guestTokenHash): void
    {
        if ($userId <= 0 || $guestTokenHash === null || $guestTokenHash === '') {
            return;
        }

        $this->db->query($this->db->update('table.pay_entitlements')->rows([
            'user_id' => $userId,
        ])->where('guest_token_hash = ?', $guestTokenHash)
            ->where('user_id IS NULL'));
    }
}
