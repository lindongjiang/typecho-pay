<?php

namespace TypechoPlugin\TypechoPay\Services;

use Typecho\Db;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

final class FulfillmentManager
{
    private Db $db;

    public function __construct(Db $db)
    {
        $this->db = $db;
    }

    public function fulfillOrder(array $order): string
    {
        $deliverables = $this->deliverablesForOrder($order);
        if (!$deliverables) {
            return 'none';
        }

        $fulfilled = 0;
        $failed = 0;

        foreach ($deliverables as $deliverable) {
            $status = $this->fulfillDeliverable($order, $deliverable);
            if ($status === 'fulfilled') {
                $fulfilled++;
            } elseif ($status === 'failed') {
                $failed++;
            }
        }

        if ($fulfilled > 0 && $failed === 0) {
            return 'fulfilled';
        }

        if ($fulfilled > 0) {
            return 'partial';
        }

        return 'failed';
    }

    private function fulfillDeliverable(array $order, array $deliverable): string
    {
        $existing = $this->findFulfillment((int) $order['id'], (int) $deliverable['id']);
        if ($existing && $existing['status'] === 'fulfilled') {
            return 'fulfilled';
        }

        try {
            $now = date('Y-m-d H:i:s');
            $this->upsertFulfillment($order, $deliverable, [
                'status' => 'processing',
                'attempts' => (int) ($existing['attempts'] ?? 0) + 1,
                'started_at' => $now,
                'updated_at' => $now,
            ]);

            $result = $this->runHandler($order, $deliverable);
            $this->upsertFulfillment($order, $deliverable, [
                'status' => 'fulfilled',
                'result_json' => json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'last_error' => null,
                'fulfilled_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            return 'fulfilled';
        } catch (\Throwable $e) {
            try {
                $this->upsertFulfillment($order, $deliverable, [
                    'status' => 'failed',
                    'last_error' => $e->getMessage(),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            } catch (\Throwable $recordError) {
                error_log('[TypechoPay] Failed to record fulfillment failure: ' . $recordError->getMessage());
            }

            return 'failed';
        }
    }

    private function runHandler(array $order, array $deliverable): array
    {
        $handler = (string) ($deliverable['handler'] ?? '');
        if (in_array($handler, ['post_access', 'content_block'], true)) {
            (new AccessService($this->db))->grant($order, [
                'id' => (int) ($deliverable['id'] ?? 0),
                'biz_type' => (string) ($deliverable['target_type'] ?: ($handler === 'post_access' ? 'post' : 'content_block')),
                'biz_id' => (int) ($deliverable['target_id'] ?? 0),
            ]);

            return ['handler' => $handler, 'granted' => true];
        }

        throw new \RuntimeException('Fulfillment handler is not implemented: ' . $handler);
    }

    private function deliverablesForOrder(array $order): array
    {
        $snapshot = $this->decodeSnapshot((string) ($order['product_snapshot_json'] ?? ''));
        $deliverables = [];
        foreach (($snapshot['deliverables'] ?? []) as $deliverable) {
            if (!is_array($deliverable)) {
                continue;
            }
            $deliverables[] = [
                'id' => (int) ($deliverable['id'] ?? 0),
                'handler' => (string) ($deliverable['handler'] ?? ''),
                'target_type' => (string) ($deliverable['target_type'] ?? ''),
                'target_id' => isset($deliverable['target_id']) ? (int) $deliverable['target_id'] : null,
                'target_key' => isset($deliverable['target_key']) ? (string) $deliverable['target_key'] : null,
            ];
        }

        if ($deliverables) {
            return $deliverables;
        }

        if (!empty($order['biz_type']) && !empty($order['biz_id'])) {
            return [[
                'id' => 0,
                'handler' => (string) $order['biz_type'] === 'content_block' ? 'content_block' : 'post_access',
                'target_type' => (string) $order['biz_type'],
                'target_id' => (int) $order['biz_id'],
                'target_key' => null,
            ]];
        }

        return [];
    }

    private function findFulfillment(int $orderId, int $deliverableId): ?array
    {
        try {
            return $this->db->fetchRow(
                $this->db->select()->from('table.pay_fulfillments')
                    ->where('order_id = ?', $orderId)
                    ->where('deliverable_id = ?', $deliverableId)
                    ->limit(1)
            ) ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function upsertFulfillment(array $order, array $deliverable, array $rows): void
    {
        $existing = $this->findFulfillment((int) $order['id'], (int) $deliverable['id']);
        if ($existing) {
            $this->db->query($this->db->update('table.pay_fulfillments')->rows($rows)->where('id = ?', (int) $existing['id']));
            return;
        }

        $now = date('Y-m-d H:i:s');
        $this->db->query($this->db->insert('table.pay_fulfillments')->rows(array_merge([
            'order_id' => (int) $order['id'],
            'deliverable_id' => (int) $deliverable['id'],
            'handler' => (string) ($deliverable['handler'] ?? ''),
            'status' => 'pending',
            'attempts' => 0,
            'card_item_id' => null,
            'result_json' => null,
            'last_error' => null,
            'started_at' => null,
            'fulfilled_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ], $rows)));
    }

    private function decodeSnapshot(string $json): array
    {
        if ($json === '') {
            return [];
        }

        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }
}
