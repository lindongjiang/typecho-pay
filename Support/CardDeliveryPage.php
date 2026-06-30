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
            . '<div><p class="typechopay-delivery__eyebrow">TypechoPay</p>'
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
:root{color-scheme:light dark;--tp-bg:#f5f1ec;--tp-panel:#fffdf9;--tp-text:#221f1c;--tp-muted:#706a63;--tp-line:rgba(38,31,24,.12);--tp-accent:#a94f4f;--tp-accent-strong:#8f3f3f;--tp-soft:#f1e4dc;--tp-code:#fbf8f3;--tp-ok:#2f7e58}*{box-sizing:border-box}body{margin:0;min-height:100vh;background:var(--tp-bg);color:var(--tp-text);font-family:-apple-system,BlinkMacSystemFont,"Segoe UI","PingFang SC","Hiragino Sans GB","Microsoft Yahei",Arial,sans-serif;line-height:1.65}.typechopay-delivery{width:min(920px,calc(100vw - 32px));margin:0 auto;padding:48px 0 56px}.typechopay-delivery__hero,.typechopay-delivery__meta,.typechopay-delivery__card,.typechopay-delivery__empty{border:1px solid var(--tp-line);border-radius:8px;background:var(--tp-panel);box-shadow:0 18px 48px rgba(42,32,24,.08)}.typechopay-delivery__hero{display:flex;align-items:flex-start;justify-content:space-between;gap:24px;padding:30px}.typechopay-delivery__eyebrow,.typechopay-delivery__card-kicker{margin:0 0 6px;color:var(--tp-accent);font-size:12px;font-weight:800;letter-spacing:0;text-transform:uppercase}.typechopay-delivery h1{margin:0;font-size:34px;line-height:1.18;letter-spacing:0}.typechopay-delivery__lead{max-width:680px;margin:12px 0 0;color:var(--tp-muted)}.typechopay-delivery__status{flex:0 0 auto;border:1px solid rgba(47,126,88,.24);border-radius:6px;padding:5px 10px;color:var(--tp-ok);background:rgba(47,126,88,.08);font-size:13px;font-weight:700}.typechopay-delivery__meta{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:0;margin-top:16px;overflow:hidden}.typechopay-delivery__meta div{padding:16px 18px;border-right:1px solid var(--tp-line)}.typechopay-delivery__meta div:last-child{border-right:0}.typechopay-delivery__meta dt{margin:0;color:var(--tp-muted);font-size:12px}.typechopay-delivery__meta dd{margin:4px 0 0;font-weight:750;word-break:break-all}.typechopay-delivery__cards{display:grid;gap:16px;margin-top:18px}.typechopay-delivery__card{padding:20px}.typechopay-delivery__card header{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin-bottom:14px}.typechopay-delivery__card h2{margin:0;font-size:20px;line-height:1.25}.typechopay-delivery__card header span{color:var(--tp-muted);font-size:13px}.typechopay-delivery__credential+.typechopay-delivery__credential{margin-top:12px}.typechopay-delivery__credential-head{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:7px}.typechopay-delivery__credential-head strong{font-size:14px}.typechopay-delivery__credential button,.typechopay-delivery__actions a{border:1px solid var(--tp-accent);border-radius:6px;background:var(--tp-accent);color:#fff;cursor:pointer;font:inherit;font-size:13px;font-weight:750;text-decoration:none}.typechopay-delivery__credential button{padding:5px 10px}.typechopay-delivery__credential button:hover,.typechopay-delivery__actions a:hover{border-color:var(--tp-accent-strong);background:var(--tp-accent-strong)}.typechopay-delivery__value{display:block;overflow:auto;margin:0;padding:13px 14px;border:1px solid var(--tp-line);border-radius:6px;background:var(--tp-code);color:var(--tp-text);font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,"Liberation Mono",monospace;font-size:14px;line-height:1.55;white-space:pre-wrap;word-break:break-word}.typechopay-delivery__empty{margin-top:18px;padding:20px;color:var(--tp-muted)}.typechopay-delivery__actions{display:flex;flex-wrap:wrap;gap:10px;margin-top:18px}.typechopay-delivery__actions a{display:inline-flex;align-items:center;justify-content:center;min-height:38px;padding:8px 16px}.typechopay-delivery__actions a+a{border-color:var(--tp-line);background:transparent;color:var(--tp-text)}@media (prefers-color-scheme:dark){:root{--tp-bg:#161413;--tp-panel:#1f1d1b;--tp-text:#eee7df;--tp-muted:#b2aaa1;--tp-line:rgba(255,255,255,.12);--tp-accent:#d18484;--tp-accent-strong:#e2a0a0;--tp-soft:#2b2422;--tp-code:#151312;--tp-ok:#78c79f}.typechopay-delivery__hero,.typechopay-delivery__meta,.typechopay-delivery__card,.typechopay-delivery__empty{box-shadow:none}.typechopay-delivery__actions a+a{color:var(--tp-text)}}@media (max-width:760px){.typechopay-delivery{width:min(100vw - 22px,920px);padding:24px 0 36px}.typechopay-delivery__hero{display:block;padding:22px}.typechopay-delivery h1{font-size:28px}.typechopay-delivery__status{display:inline-flex;margin-top:16px}.typechopay-delivery__meta{grid-template-columns:1fr 1fr}.typechopay-delivery__meta div{border-right:0;border-bottom:1px solid var(--tp-line)}.typechopay-delivery__meta div:nth-last-child(-n+2){border-bottom:0}.typechopay-delivery__card{padding:16px}.typechopay-delivery__card header{display:block}.typechopay-delivery__card header span{display:block;margin-top:8px}.typechopay-delivery__actions a{flex:1 1 160px}}@media (max-width:460px){.typechopay-delivery__meta{grid-template-columns:1fr}.typechopay-delivery__meta div{border-bottom:1px solid var(--tp-line)}.typechopay-delivery__meta div:last-child{border-bottom:0}.typechopay-delivery__credential-head{align-items:flex-start}.typechopay-delivery__credential button{flex:0 0 auto}}
CSS;
    }

    private static function script(): string
    {
        return <<<'JS'
(function(){function copyText(text){if(navigator.clipboard&&window.isSecureContext){return navigator.clipboard.writeText(text)}return new Promise(function(resolve,reject){var area=document.createElement("textarea");area.value=text;area.setAttribute("readonly","readonly");area.style.position="fixed";area.style.left="-9999px";document.body.appendChild(area);area.select();try{document.execCommand("copy")?resolve():reject(new Error("copy failed"))}catch(error){reject(error)}document.body.removeChild(area)})}document.addEventListener("click",function(event){if(!event.target||!event.target.closest){return}var button=event.target.closest("[data-copy-target]");if(!button){return}var value=document.getElementById(button.getAttribute("data-copy-target"));if(!value){return}var original=button.textContent;copyText(value.textContent).then(function(){button.textContent="已复制";window.setTimeout(function(){button.textContent=original},1400)}).catch(function(){button.textContent="复制失败";window.setTimeout(function(){button.textContent=original},1600)})})}());
JS;
    }
}
