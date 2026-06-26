<?php

namespace TypechoPlugin\TypechoPay\Services;

use Typecho\Db;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

final class NonceService
{
    private const TTL = 600;

    private Db $db;

    public function __construct(Db $db)
    {
        $this->db = $db;
    }

    public function consume(string $scope, string $nonce): void
    {
        $this->deleteExpired();

        $now = date('Y-m-d H:i:s');
        try {
            $this->db->query($this->db->insert('table.pay_nonces')->rows([
                'nonce_hash' => hash('sha256', $scope . ':' . $nonce),
                'scope' => $scope,
                'expires_at' => date('Y-m-d H:i:s', time() + self::TTL),
                'created_at' => $now,
            ]));
        } catch (\Throwable $e) {
            throw new \InvalidArgumentException('Payment payload was already used.');
        }
    }

    private function deleteExpired(): void
    {
        try {
            $this->db->query(
                $this->db->delete('table.pay_nonces')->where('expires_at < ?', date('Y-m-d H:i:s'))
            );
        } catch (\Throwable $e) {
            // A missing nonce table should surface through consume(); cleanup itself is non-critical.
        }
    }
}
