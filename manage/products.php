<?php

use Typecho\Db;
use TypechoPlugin\TypechoPay\Services\CardCodeService;
use TypechoPlugin\TypechoPay\Support\Money;
use Widget\Notice;

if (!defined('__TYPECHO_ADMIN__')) {
    exit;
}

$db = Db::get();
$cardService = new CardCodeService($db);
$panelUrl = $options->adminUrl . 'extending.php?panel=TypechoPay%2Fmanage%2Fproducts.php';
$formAction = $security->getTokenUrl($request->getRequestUrl());

if ($request->isPost()) {
    $security->protect();

    try {
        $action = (string) $request->get('action');
        if ($action === 'create_product') {
            $productKey = trim((string) $request->get('product_key'));
            if (!preg_match('/^[a-zA-Z0-9_.:-]{1,128}$/', $productKey)) {
                throw new InvalidArgumentException('商品标识只允许字母、数字、点、横线、下划线和冒号。');
            }

            $title = trim((string) $request->get('title'));
            $titleLength = function_exists('mb_strlen') ? mb_strlen($title) : strlen($title);
            if ($title === '' || $titleLength > 255) {
                throw new InvalidArgumentException('请填写 1-255 字的商品标题。');
            }

            $amount = Money::assertAmount($request->get('amount'));
            $currency = Money::assertCurrency($request->get('currency'));
            $policy = strtolower(trim((string) $request->get('purchase_policy'))) ?: 'repeatable';
            if (!in_array($policy, ['once', 'repeatable', 'limited'], true)) {
                throw new InvalidArgumentException('购买策略无效。');
            }

            $contentId = filter_var($request->get('content_id'), FILTER_VALIDATE_INT);
            $contentId = $contentId !== false && (int) $contentId > 0 ? (int) $contentId : null;
            $enablePostAccess = (string) $request->get('enable_post_access') === '1';
            $enableCardcode = (string) $request->get('enable_cardcode') === '1';
            if (!$enablePostAccess && !$enableCardcode) {
                throw new InvalidArgumentException('请至少选择一种交付内容。');
            }

            if ($enablePostAccess && $contentId === null) {
                throw new InvalidArgumentException('解锁文章需要填写文章 cid。');
            }

            $now = date('Y-m-d H:i:s');
            $productId = $db->query($db->insert('table.pay_products')->rows([
                'product_key' => $productKey,
                'title' => $title,
                'content_id' => $contentId,
                'amount' => $amount,
                'currency' => $currency,
                'status' => 'active',
                'allow_guest' => 1,
                'purchase_policy' => $policy,
                'max_per_user' => null,
                'duration_seconds' => null,
                'version' => 1,
                'stock_policy' => $enableCardcode ? 'reserve_on_order' : 'none',
                'created_at' => $now,
                'updated_at' => $now,
            ]));

            if ($enablePostAccess) {
                $db->query($db->insert('table.pay_product_deliverables')->rows([
                    'product_id' => (int) $productId,
                    'handler' => 'post_access',
                    'target_type' => 'post',
                    'target_id' => $contentId,
                    'target_key' => null,
                    'config_json' => null,
                    'sort_order' => 10,
                    'enabled' => 1,
                ]));
            }

            if ($enableCardcode) {
                $db->query($db->insert('table.pay_product_deliverables')->rows([
                    'product_id' => (int) $productId,
                    'handler' => 'cardcode',
                    'target_type' => 'cardcode',
                    'target_id' => null,
                    'target_key' => 'default',
                    'config_json' => null,
                    'sort_order' => 20,
                    'enabled' => 1,
                ]));
            }

            Notice::alloc()->set(_t('商品已创建，可使用短代码 [typechopay product="%s"]。', $productKey), 'success');
        } elseif ($action === 'import_cards') {
            $productId = filter_var($request->get('product_id'), FILTER_VALIDATE_INT);
            if ($productId === false || (int) $productId <= 0) {
                throw new InvalidArgumentException('请选择卡密商品。');
            }

            $result = $cardService->importBatch(
                (int) $productId,
                trim((string) $request->get('batch_name')),
                (string) $request->get('card_lines'),
                $user->hasLogin() ? (int) $user->uid : null
            );
            Notice::alloc()->set(
                _t('卡密导入完成：成功 %d 条，重复/失败 %d 条。', $result['imported'], $result['duplicates']),
                $result['imported'] > 0 ? 'success' : 'notice'
            );
        }
    } catch (Throwable $e) {
        Notice::alloc()->set($e->getMessage(), 'error');
    }

    $response->redirect($panelUrl);
    return;
}

$products = $db->fetchAll($db->select()->from('table.pay_products')->order('created_at', Db::SORT_DESC));
$productHandlers = [];
if ($products) {
    $deliverables = $db->fetchAll($db->select()->from('table.pay_product_deliverables')->order('sort_order', Db::SORT_ASC));
    foreach ($deliverables as $deliverable) {
        $productHandlers[(int) $deliverable['product_id']][] = (string) $deliverable['handler'];
    }
}

include 'header.php';
include 'menu.php';
?>

<div class="main">
    <div class="body container">
        <?php include 'page-title.php'; ?>

        <div class="typecho-list-operate clearfix">
            <p>这里管理服务端商品价格、购买策略和卡密库存。正式卡密商品请使用 <code>[typechopay product="商品标识"]</code>。</p>
        </div>

        <div class="table-description" style="margin-top:20px;">
            <h3><?php _e('创建商品'); ?></h3>
            <form method="post" action="<?php echo htmlspecialchars($formAction); ?>">
                <input type="hidden" name="action" value="create_product">
                <p>
                    <label><?php _e('商品标识'); ?></label><br>
                    <input type="text" name="product_key" placeholder="recharge-card-100" style="width:260px;" required>
                </p>
                <p>
                    <label><?php _e('商品标题'); ?></label><br>
                    <input type="text" name="title" placeholder="100 元充值卡" style="width:360px;" required>
                </p>
                <p>
                    <label><?php _e('金额'); ?></label><br>
                    <input type="number" name="amount" min="1" placeholder="CNY 用分，JPY 用日元" style="width:180px;" required>
                    <select name="currency">
                        <option value="CNY">CNY</option>
                        <option value="JPY">JPY</option>
                    </select>
                </p>
                <p>
                    <label><?php _e('购买策略'); ?></label><br>
                    <select name="purchase_policy">
                        <option value="repeatable"><?php _e('repeatable - 可重复购买，适合卡密'); ?></option>
                        <option value="once"><?php _e('once - 已购买后不再显示付款按钮'); ?></option>
                        <option value="limited"><?php _e('limited - 预留策略，后续扩展'); ?></option>
                    </select>
                </p>
                <p>
                    <label><?php _e('文章 cid（可选）'); ?></label><br>
                    <input type="number" name="content_id" min="1" placeholder="组合商品解锁文章时填写" style="width:220px;">
                </p>
                <p>
                    <label><input type="checkbox" name="enable_cardcode" value="1" checked> <?php _e('交付卡密'); ?></label>
                    <label style="margin-left:18px;"><input type="checkbox" name="enable_post_access" value="1"> <?php _e('同时解锁文章'); ?></label>
                </p>
                <p><button class="btn primary" type="submit"><?php _e('创建商品'); ?></button></p>
            </form>
        </div>

        <div class="table-description" style="margin-top:30px;">
            <h3><?php _e('导入卡密'); ?></h3>
            <form method="post" action="<?php echo htmlspecialchars($formAction); ?>">
                <input type="hidden" name="action" value="import_cards">
                <p>
                    <label><?php _e('选择商品'); ?></label><br>
                    <select name="product_id" required>
                        <?php foreach ($products as $product): ?>
                            <?php $handlers = $productHandlers[(int) $product['id']] ?? []; ?>
                            <?php if (in_array('cardcode', $handlers, true)): ?>
                                <option value="<?php echo (int) $product['id']; ?>">
                                    <?php echo htmlspecialchars($product['product_key'] . ' - ' . $product['title']); ?>
                                </option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </p>
                <p>
                    <label><?php _e('批次名称'); ?></label><br>
                    <input type="text" name="batch_name" placeholder="2026-06-26 首批导入" style="width:320px;">
                </p>
                <p>
                    <label><?php _e('卡密内容'); ?></label><br>
                    <textarea name="card_lines" rows="8" style="width:100%;" placeholder="每行一张。支持：卡号----卡密、卡号|卡密、卡号,卡密、或单独兑换码。" required></textarea>
                </p>
                <p><button class="btn primary" type="submit"><?php _e('导入卡密'); ?></button></p>
            </form>
        </div>

        <div class="typecho-table-wrap" style="margin-top:30px;">
            <table class="typecho-list-table">
                <colgroup>
                    <col width="18%">
                    <col width="22%">
                    <col width="10%">
                    <col width="12%">
                    <col width="18%">
                    <col width="20%">
                </colgroup>
                <thead>
                <tr>
                    <th><?php _e('商品标识'); ?></th>
                    <th><?php _e('标题'); ?></th>
                    <th><?php _e('金额'); ?></th>
                    <th><?php _e('策略'); ?></th>
                    <th><?php _e('库存'); ?></th>
                    <th><?php _e('短代码'); ?></th>
                </tr>
                </thead>
                <tbody>
                <?php if (!$products): ?>
                    <tr><td colspan="6"><h6 class="typecho-list-table-title"><?php _e('暂无商品'); ?></h6></td></tr>
                <?php endif; ?>
                <?php foreach ($products as $product): ?>
                    <?php
                    $handlers = $productHandlers[(int) $product['id']] ?? [];
                    $counts = in_array('cardcode', $handlers, true)
                        ? $cardService->stockCounts((int) $product['id'])
                        : ['available' => 0, 'reserved' => 0, 'delivered' => 0, 'total' => 0];
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($product['product_key']); ?></td>
                        <td>
                            <?php echo htmlspecialchars($product['title']); ?>
                            <br><small><?php echo htmlspecialchars(implode(', ', $handlers)); ?></small>
                        </td>
                        <td><?php echo htmlspecialchars($product['currency'] . ' ' . $product['amount']); ?></td>
                        <td><?php echo htmlspecialchars($product['purchase_policy']); ?></td>
                        <td>
                            <?php if (in_array('cardcode', $handlers, true)): ?>
                                <?php echo htmlspecialchars('可用 ' . $counts['available'] . ' / 预留 ' . $counts['reserved'] . ' / 已发 ' . $counts['delivered'] . ' / 总计 ' . $counts['total']); ?>
                            <?php else: ?>
                                <?php _e('无卡密'); ?>
                            <?php endif; ?>
                        </td>
                        <td><code><?php echo htmlspecialchars('[typechopay product="' . $product['product_key'] . '"]'); ?></code></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
