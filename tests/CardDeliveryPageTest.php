<?php

define('__TYPECHO_ROOT_DIR__', dirname(__DIR__, 4));

require_once dirname(__DIR__) . '/Support/Money.php';
require_once dirname(__DIR__) . '/Support/CardDeliveryPage.php';

use TypechoPlugin\TypechoPay\Support\CardDeliveryPage;

$passed = 0;
$failed = 0;

function cdp_assert(bool $condition, string $label): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo '.';
    } else {
        $failed++;
        echo "\nFAIL: {$label}\n";
    }
}

$html = CardDeliveryPage::render(
    [
        'out_trade_no' => 'PAY-20260630-0001',
        'status' => 'paid',
        'fulfillment_status' => 'fulfilled',
        'amount' => 100,
        'currency' => 'CNY',
    ],
    [
        [
            'code' => 'CARD-<script>alert(1)</script>',
            'secret' => 'SECRET-001',
            'delivered_at' => '2026-06-30 12:00:00',
        ],
    ],
    '/action/typechopay?do=delivery&out_trade_no=PAY-20260630-0001',
    '/archives/11/'
);

cdp_assert(strpos($html, '卡密已交付') !== false, 'Delivery page shows delivered title');
cdp_assert(strpos($html, 'typechopay-delivery__credential') !== false, 'Delivery page renders credential fields');
cdp_assert(strpos($html, 'data-copy-target=') !== false, 'Delivery page provides copy buttons');
cdp_assert(strpos($html, '卡密 / 密钥') !== false, 'Delivery page separates card secret from card code');
cdp_assert(strpos($html, '刷新交付状态') !== false, 'Delivery page keeps refresh action');
cdp_assert(strpos($html, '返回原页面') !== false, 'Delivery page keeps return action');
cdp_assert(strpos($html, '<script>alert(1)</script>') === false, 'Delivery page escapes card code HTML');
cdp_assert(strpos($html, 'CARD-&lt;script&gt;alert(1)&lt;/script&gt;') !== false, 'Delivery page preserves escaped card code text');
cdp_assert(strpos($html, '1.00') !== false, 'Delivery page renders CNY amount');
cdp_assert(strpos($html, 'navigator.clipboard') !== false, 'Delivery page includes clipboard enhancement');

echo "\n\n--- CardDeliveryPageTest ---\n";
echo "Passed: {$passed}\n";
echo "Failed: {$failed}\n";
exit($failed > 0 ? 1 : 0);
