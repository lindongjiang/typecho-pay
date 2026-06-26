<?php

namespace TypechoPlugin\TypechoPay\Services;

use Typecho\Db;
use TypechoPlugin\TypechoPay\Support\CardCodeCipher;
use Widget\Options;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

final class CardCodeService
{
    private const RESERVATION_TTL = 1800;

    private Db $db;

    public function __construct(Db $db)
    {
        $this->db = $db;
    }

    public function importBatch(int $productId, string $batchName, string $rawLines, ?int $importedBy = null): array
    {
        if ($productId <= 0) {
            throw new \InvalidArgumentException('Invalid product id.');
        }

        $items = $this->parseLines($rawLines);
        if (!$items) {
            throw new \InvalidArgumentException('No card codes to import.');
        }

        $now = date('Y-m-d H:i:s');
        $batchId = $this->db->query($this->db->insert('table.pay_card_batches')->rows([
            'product_id' => $productId,
            'batch_name' => $batchName !== '' ? $batchName : 'batch-' . date('YmdHis'),
            'imported_count' => 0,
            'imported_by' => $importedBy,
            'created_at' => $now,
        ]));

        $imported = 0;
        $duplicates = 0;
        foreach ($items as $item) {
            $fingerprint = $this->fingerprint($productId, $item['code'], $item['secret']);
            $codeCiphertext = CardCodeCipher::encrypt($item['code'], $this->keyMaterial());
            $secretCiphertext = $item['secret'] !== null
                ? CardCodeCipher::encrypt($item['secret'], $this->keyMaterial())
                : null;
            try {
                $this->db->query($this->db->insert('table.pay_card_items')->rows([
                    'product_id' => $productId,
                    'batch_id' => (int) $batchId,
                    'code_ciphertext' => $codeCiphertext,
                    'secret_ciphertext' => $secretCiphertext,
                    'fingerprint' => $fingerprint,
                    'status' => 'available',
                    'reserved_order_id' => null,
                    'reserved_until' => null,
                    'delivered_order_id' => null,
                    'delivered_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));
                $imported++;
            } catch (\Throwable $e) {
                $duplicates++;
            }
        }

        $this->db->query($this->db->update('table.pay_card_batches')->rows([
            'imported_count' => $imported,
        ])->where('id = ?', (int) $batchId));

        return [
            'batch_id' => (int) $batchId,
            'imported' => $imported,
            'duplicates' => $duplicates,
            'total' => count($items),
        ];
    }

    public function reserveForOrder(array $order): ?array
    {
        $productId = (int) ($order['product_id'] ?? 0);
        $orderId = (int) ($order['id'] ?? 0);
        if ($productId <= 0 || $orderId <= 0) {
            return null;
        }

        $this->releaseExpiredReservations($productId);

        $existing = $this->findOrderCard($productId, $orderId, ['reserved', 'delivered']);
        if ($existing) {
            return $existing;
        }

        $reservedUntil = date('Y-m-d H:i:s', time() + self::RESERVATION_TTL);
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $candidate = $this->db->fetchRow(
                $this->db->select()->from('table.pay_card_items')
                    ->where('product_id = ?', $productId)
                    ->where('status = ?', 'available')
                    ->order('id', Db::SORT_ASC)
                    ->limit(1)
            );

            if (!$candidate) {
                throw new \InvalidArgumentException('Card code is out of stock.');
            }

            $updated = $this->db->query($this->db->update('table.pay_card_items')->rows([
                'status' => 'reserved',
                'reserved_order_id' => $orderId,
                'reserved_until' => $reservedUntil,
                'updated_at' => date('Y-m-d H:i:s'),
            ])->where('id = ?', (int) $candidate['id'])
                ->where('status = ?', 'available'));

            if ($updated > 0) {
                return $this->findById((int) $candidate['id']);
            }
        }

        throw new \RuntimeException('Failed to reserve card code.');
    }

    public function deliverForOrder(array $order): array
    {
        $productId = (int) ($order['product_id'] ?? 0);
        $orderId = (int) ($order['id'] ?? 0);
        if ($productId <= 0 || $orderId <= 0) {
            throw new \RuntimeException('Invalid card-code order.');
        }

        $delivered = $this->findOrderCard($productId, $orderId, ['delivered']);
        if ($delivered) {
            return $delivered;
        }

        $this->releaseExpiredReservations($productId);
        $reserved = $this->findOrderCard($productId, $orderId, ['reserved']);
        if (!$reserved) {
            $reserved = $this->reserveForOrder($order);
        }

        if (!$reserved) {
            throw new \RuntimeException('Card-code reservation failed.');
        }

        $updated = $this->db->query($this->db->update('table.pay_card_items')->rows([
            'status' => 'delivered',
            'delivered_order_id' => $orderId,
            'delivered_at' => date('Y-m-d H:i:s'),
            'reserved_until' => null,
            'updated_at' => date('Y-m-d H:i:s'),
        ])->where('id = ?', (int) $reserved['id'])
            ->where('status = ?', 'reserved')
            ->where('reserved_order_id = ?', $orderId));

        if ($updated <= 0) {
            $delivered = $this->findOrderCard($productId, $orderId, ['delivered']);
            if ($delivered) {
                return $delivered;
            }

            throw new \RuntimeException('Failed to deliver card code.');
        }

        return $this->findById((int) $reserved['id']) ?: $reserved;
    }

    public function releaseOrderReservations(array $order): void
    {
        $orderId = (int) ($order['id'] ?? 0);
        if ($orderId <= 0) {
            return;
        }

        $this->db->query($this->db->update('table.pay_card_items')->rows([
            'status' => 'available',
            'reserved_order_id' => null,
            'reserved_until' => null,
            'updated_at' => date('Y-m-d H:i:s'),
        ])->where('reserved_order_id = ?', $orderId)
            ->where('status = ?', 'reserved'));
    }

    public function deliveredCardsForOrder(array $order): array
    {
        $orderId = (int) ($order['id'] ?? 0);
        if ($orderId <= 0) {
            return [];
        }

        $rows = $this->db->fetchAll(
            $this->db->select()->from('table.pay_card_items')
                ->where('delivered_order_id = ?', $orderId)
                ->where('status = ?', 'delivered')
                ->order('delivered_at', Db::SORT_ASC)
        );

        $cards = [];
        foreach ($rows as $row) {
            $cards[] = $this->decryptRow($row);
        }

        return $cards;
    }

    public function stockCounts(int $productId): array
    {
        $counts = [
            'available' => 0,
            'reserved' => 0,
            'delivered' => 0,
            'void' => 0,
            'compromised' => 0,
            'total' => 0,
        ];

        if ($productId <= 0) {
            return $counts;
        }

        $this->releaseExpiredReservations($productId);
        $table = $this->quotedTable('pay_card_items');
        $rows = $this->db->fetchAll(
            "SELECT status, COUNT(*) AS count_value FROM {$table} WHERE product_id = " . (int) $productId . " GROUP BY status"
        );

        foreach ($rows as $row) {
            $status = (string) ($row['status'] ?? '');
            $count = (int) ($row['count_value'] ?? 0);
            if (array_key_exists($status, $counts)) {
                $counts[$status] = $count;
            }
            $counts['total'] += $count;
        }

        return $counts;
    }

    public function releaseExpiredReservations(?int $productId = null): void
    {
        $update = $this->db->update('table.pay_card_items')->rows([
            'status' => 'available',
            'reserved_order_id' => null,
            'reserved_until' => null,
            'updated_at' => date('Y-m-d H:i:s'),
        ])->where('status = ?', 'reserved')
            ->where('reserved_until IS NOT NULL')
            ->where('reserved_until < ?', date('Y-m-d H:i:s'));

        if ($productId !== null && $productId > 0) {
            $update->where('product_id = ?', $productId);
        }

        $this->db->query($update);
    }

    private function findOrderCard(int $productId, int $orderId, array $statuses): ?array
    {
        return $this->db->fetchRow(
            $this->db->select()->from('table.pay_card_items')
                ->where('product_id = ?', $productId)
                ->where('(reserved_order_id = ? OR delivered_order_id = ?)', $orderId, $orderId)
                ->where('status IN ?', $statuses)
                ->order('id', Db::SORT_ASC)
                ->limit(1)
        ) ?: null;
    }

    private function findById(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        return $this->db->fetchRow(
            $this->db->select()->from('table.pay_card_items')->where('id = ?', $id)->limit(1)
        ) ?: null;
    }

    private function parseLines(string $rawLines): array
    {
        $items = [];
        $seen = [];
        foreach (preg_split('/\R/u', $rawLines) ?: [] as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }

            [$code, $secret] = $this->parseLine($line);
            $key = hash('sha256', $code . "\0" . (string) $secret);
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $items[] = ['code' => $code, 'secret' => $secret];
        }

        return $items;
    }

    private function parseLine(string $line): array
    {
        foreach (["\t", '----', '---', '|', ','] as $separator) {
            if (strpos($line, $separator) !== false) {
                [$code, $secret] = array_map('trim', explode($separator, $line, 2));
                if ($code === '') {
                    throw new \InvalidArgumentException('Card code line contains empty code.');
                }

                return [$code, $secret !== '' ? $secret : null];
            }
        }

        return [$line, null];
    }

    private function decryptRow(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'code' => CardCodeCipher::decrypt((string) $row['code_ciphertext'], $this->keyMaterial()),
            'secret' => !empty($row['secret_ciphertext'])
                ? CardCodeCipher::decrypt((string) $row['secret_ciphertext'], $this->keyMaterial())
                : null,
            'delivered_at' => $row['delivered_at'] ?? null,
        ];
    }

    private function fingerprint(int $productId, string $code, ?string $secret): string
    {
        return hash_hmac('sha256', $productId . "\0" . $code . "\0" . (string) $secret, $this->keyMaterial());
    }

    private function keyMaterial(): string
    {
        return (string) Options::alloc()->secret;
    }

    private function quotedTable(string $table): string
    {
        $prefix = $this->db->getPrefix();
        $adapter = strtolower($this->db->getAdapterName());
        if (strpos($adapter, 'mysql') !== false || strpos($adapter, 'mysqli') !== false) {
            return '`' . str_replace('`', '``', $prefix . $table) . '`';
        }

        return '"' . str_replace('"', '""', $prefix . $table) . '"';
    }
}
