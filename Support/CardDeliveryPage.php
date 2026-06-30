<?php

namespace TypechoPlugin\TypechoPay\Support;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

final class CardDeliveryPage
{
    public static function render(array $order, array $cards, string $deliveryUrl, string $returnTo): string
    {
        $isPaid = (string) ($order['status'] ?? '') === 'paid';
        $hasCards = !empty($cards);
        $title = $hasCards ? '卡密已交付' : ($isPaid ? '交付处理中' : '等待支付完成');
        $lead = $hasCards
            ? '以下为本次订单交付的卡密，请妥善保存。页面不会被缓存，也不会被搜索引擎索引。'
            : ($isPaid ? '支付已完成，但卡密暂未交付完成。请刷新或联系站点管理员处理。' : '订单尚未完成支付，暂不能查看卡密。');

        $html = '<!doctype html><html lang="zh-CN"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>' . self::e($title) . '</title>'
            . '<meta name="robots" content="noindex, nofollow">'
            . '<style>' . self::style() . '</style>'
            . '</head><body><main class="typechopay-delivery">'
            . '<section class="typechopay-delivery__hero">'
            . '<div><p class="typechopay-delivery__eyebrow">卡密交付</p>'
            . '<h1>' . self::e($title) . '</h1>'
            . '<p class="typechopay-delivery__lead">' . self::e($lead) . '</p></div>'
            . '<span class="typechopay-delivery__status">' . self::e((string) ($order['status'] ?? 'unknown')) . '</span>'
            . '</section>'
            . self::renderOrderMeta($order);

        if ($hasCards) {
            $html .= '<section class="typechopay-delivery__cards" aria-label="已交付卡密">';
            foreach ($cards as $index => $card) {
                $number = $index + 1;
                $html .= '<article class="typechopay-delivery__card">'
                    . '<header><div><p class="typechopay-delivery__card-kicker">卡密 ' . $number . '</p>'
                    . '<h2>交付凭证</h2></div>'
                    . '<span>' . self::e((string) ($card['delivered_at'] ?? '')) . '</span></header>'
                    . self::renderCredentialField('卡号 / 兑换码', (string) ($card['code'] ?? ''), 'typechopay-card-' . $number . '-code');

                $secret = $card['secret'] ?? null;
                if ($secret !== null && $secret !== '') {
                    $html .= self::renderCredentialField('卡密 / 密钥', (string) $secret, 'typechopay-card-' . $number . '-secret');
                }

                $html .= '</article>';
            }
            $html .= '</section>';
        } else {
            $html .= '<section class="typechopay-delivery__empty">' . self::e($lead) . '</section>';
        }

        $html .= '<nav class="typechopay-delivery__actions" aria-label="页面操作">'
            . '<a href="' . self::e($deliveryUrl) . '">刷新交付状态</a>';
        if ($returnTo !== '') {
            $html .= '<a href="' . self::e($returnTo) . '">返回原页面</a>';
        }
        $html .= '</nav>'
            . '<script>' . self::script() . '</script>'
            . '</main></body></html>';

        return $html;
    }

    private static function renderOrderMeta(array $order): string
    {
        $items = [
            '订单号' => (string) ($order['out_trade_no'] ?? ''),
            '订单状态' => (string) ($order['status'] ?? 'unknown'),
            '交付状态' => (string) ($order['fulfillment_status'] ?? ''),
        ];

        if (isset($order['amount'], $order['currency'])) {
            $items['支付金额'] = Money::formatForDisplay((int) $order['amount'], (string) $order['currency']);
        }

        $html = '<dl class="typechopay-delivery__meta" aria-label="订单信息">';
        foreach ($items as $label => $value) {
            if ($value === '') {
                continue;
            }
            $html .= '<div><dt>' . self::e($label) . '</dt><dd>' . self::e($value) . '</dd></div>';
        }
        return $html . '</dl>';
    }

    private static function renderCredentialField(string $label, string $value, string $id): string
    {
        return '<div class="typechopay-delivery__credential">'
            . '<div class="typechopay-delivery__credential-head">'
            . '<strong>' . self::e($label) . '</strong>'
            . '<button type="button" data-copy-target="' . self::e($id) . '">复制</button>'
            . '</div>'
            . '<pre id="' . self::e($id) . '" class="typechopay-delivery__value">' . self::e($value) . '</pre>'
            . '</div>';
    }

    private static function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    private static function style(): string
    {
        return <<<'CSS'
:root {
    color-scheme: light dark;
    --tp-bg: #f7f7f3;
    --tp-panel: #fffdf8;
    --tp-panel-strong: #f0f2ed;
    --tp-text: #20211d;
    --tp-muted: #676a61;
    --tp-line: rgba(42, 45, 39, 0.16);
    --tp-accent: #3f6f5b;
    --tp-accent-strong: #2e5747;
    --tp-warm: #a7652a;
    --tp-code: #f2f4ee;
    --tp-ok: #3f7d5a;
    --tp-shadow: 0 10px 28px rgba(32, 33, 29, 0.08);
}

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    min-height: 100vh;
    background: var(--tp-bg);
    color: var(--tp-text);
    font-family: -apple-system, BlinkMacSystemFont, "SF Pro Text", "Segoe UI", "PingFang SC", "Hiragino Sans GB", "Microsoft Yahei", Arial, sans-serif;
    line-height: 1.65;
    letter-spacing: 0;
}

.typechopay-delivery {
    width: min(920px, calc(100vw - 32px));
    margin: 0 auto;
    padding: 48px 0 56px;
}

.typechopay-delivery__hero,
.typechopay-delivery__meta,
.typechopay-delivery__card,
.typechopay-delivery__empty {
    border: 1px solid var(--tp-line);
    border-radius: 8px;
    background: var(--tp-panel);
    box-shadow: var(--tp-shadow);
}

.typechopay-delivery__hero {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 24px;
    border-left: 4px solid var(--tp-accent);
    padding: 30px;
}

.typechopay-delivery__eyebrow,
.typechopay-delivery__card-kicker {
    margin: 0 0 6px;
    color: var(--tp-accent);
    font-size: 12px;
    font-weight: 800;
    letter-spacing: 0;
    text-transform: uppercase;
}

.typechopay-delivery h1 {
    margin: 0;
    font-size: 34px;
    line-height: 1.18;
    letter-spacing: 0;
}

.typechopay-delivery__lead {
    max-width: 680px;
    margin: 12px 0 0;
    color: var(--tp-muted);
}

.typechopay-delivery__status {
    flex: 0 0 auto;
    border: 1px solid rgba(63, 125, 90, 0.3);
    border-radius: 999px;
    padding: 5px 11px;
    color: var(--tp-ok);
    background: rgba(63, 125, 90, 0.1);
    font-size: 13px;
    font-weight: 750;
}

.typechopay-delivery__meta {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 1px;
    margin-top: 16px;
    overflow: hidden;
    background: var(--tp-line);
}

.typechopay-delivery__meta div {
    padding: 16px 18px;
    background: var(--tp-panel);
}

.typechopay-delivery__meta dt {
    margin: 0;
    color: var(--tp-muted);
    font-size: 12px;
}

.typechopay-delivery__meta dd {
    margin: 4px 0 0;
    font-weight: 760;
    word-break: break-all;
}

.typechopay-delivery__cards {
    display: grid;
    gap: 16px;
    margin-top: 18px;
}

.typechopay-delivery__card {
    padding: 20px;
    border-left: 4px solid var(--tp-warm);
}

.typechopay-delivery__card header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 14px;
}

.typechopay-delivery__card h2 {
    margin: 0;
    font-size: 20px;
    line-height: 1.25;
}

.typechopay-delivery__card header span {
    color: var(--tp-muted);
    font-size: 13px;
}

.typechopay-delivery__credential + .typechopay-delivery__credential {
    margin-top: 12px;
}

.typechopay-delivery__credential-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 7px;
}

.typechopay-delivery__credential-head strong {
    font-size: 14px;
}

.typechopay-delivery__credential button,
.typechopay-delivery__actions a {
    border: 1px solid var(--tp-accent);
    border-radius: 8px;
    background: var(--tp-accent);
    color: #fffdf8;
    cursor: pointer;
    font: inherit;
    font-size: 13px;
    font-weight: 750;
    text-decoration: none;
}

.typechopay-delivery__credential button {
    padding: 6px 11px;
}

.typechopay-delivery__credential button:hover,
.typechopay-delivery__actions a:hover {
    border-color: var(--tp-accent-strong);
    background: var(--tp-accent-strong);
}

.typechopay-delivery__value {
    display: block;
    overflow: auto;
    margin: 0;
    padding: 13px 14px;
    border: 1px solid var(--tp-line);
    border-radius: 8px;
    background: var(--tp-code);
    color: var(--tp-text);
    font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, "Liberation Mono", monospace;
    font-size: 14px;
    line-height: 1.55;
    white-space: pre-wrap;
    word-break: break-word;
}

.typechopay-delivery__empty {
    margin-top: 18px;
    padding: 20px;
    color: var(--tp-muted);
}

.typechopay-delivery__actions {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 18px;
}

.typechopay-delivery__actions a {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 40px;
    padding: 9px 16px;
}

.typechopay-delivery__actions a + a {
    border-color: var(--tp-line);
    background: var(--tp-panel-strong);
    color: var(--tp-text);
}

@media (prefers-color-scheme: dark) {
    :root {
        --tp-bg: #11130f;
        --tp-panel: #191c17;
        --tp-panel-strong: #22261f;
        --tp-text: #f1f2ec;
        --tp-muted: #a8aa9f;
        --tp-line: rgba(241, 242, 236, 0.14);
        --tp-accent: #78aa8f;
        --tp-accent-strong: #91bea6;
        --tp-warm: #d39a5a;
        --tp-code: #0b100d;
        --tp-ok: #86bd9b;
        --tp-shadow: none;
    }
}

@media (max-width: 760px) {
    .typechopay-delivery {
        width: min(100vw - 22px, 920px);
        padding: 24px 0 36px;
    }

    .typechopay-delivery__hero {
        display: block;
        padding: 22px;
    }

    .typechopay-delivery h1 {
        font-size: 28px;
    }

    .typechopay-delivery__status {
        display: inline-flex;
        margin-top: 16px;
    }

    .typechopay-delivery__card {
        padding: 16px;
    }

    .typechopay-delivery__card header {
        display: block;
    }

    .typechopay-delivery__card header span {
        display: block;
        margin-top: 8px;
    }

    .typechopay-delivery__actions a {
        flex: 1 1 160px;
    }
}

@media (max-width: 460px) {
    .typechopay-delivery__meta {
        grid-template-columns: 1fr;
    }

    .typechopay-delivery__credential-head {
        align-items: flex-start;
    }

    .typechopay-delivery__credential button {
        flex: 0 0 auto;
    }
}
CSS;
    }

    private static function script(): string
    {
        return <<<'JS'
(function(){function copyText(text){if(navigator.clipboard&&window.isSecureContext){return navigator.clipboard.writeText(text)}return new Promise(function(resolve,reject){var area=document.createElement("textarea");area.value=text;area.setAttribute("readonly","readonly");area.style.position="fixed";area.style.left="-9999px";document.body.appendChild(area);area.select();try{document.execCommand("copy")?resolve():reject(new Error("copy failed"))}catch(error){reject(error)}document.body.removeChild(area)})}document.addEventListener("click",function(event){if(!event.target||!event.target.closest){return}var button=event.target.closest("[data-copy-target]");if(!button){return}var value=document.getElementById(button.getAttribute("data-copy-target"));if(!value){return}var original=button.textContent;copyText(value.textContent).then(function(){button.textContent="已复制";window.setTimeout(function(){button.textContent=original},1400)}).catch(function(){button.textContent="复制失败";window.setTimeout(function(){button.textContent=original},1600)})})}());
JS;
    }
}
