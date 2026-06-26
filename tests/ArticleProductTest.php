<?php

/**
 * Static regression checks for the article-as-product frontend integration.
 */

$root = dirname(__DIR__);
$passed = 0;
$failed = 0;

function ap_assert(bool $condition, string $label): void
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

$pluginSource = file_get_contents($root . '/Plugin.php');
$cssSource = file_get_contents($root . '/assets/typechopay.css');

ap_assert(strpos($pluginSource, 'productAutoInjectPosition') !== false, 'Plugin has auto-inject config');
ap_assert(strpos($pluginSource, "factory('admin/write-post.php')->content") !== false, 'Registers post editor content hook');
ap_assert(strpos($pluginSource, "factory('admin/write-page.php')->content") !== false, 'Registers page editor content hook');
ap_assert(strpos($pluginSource, "Widget\\Contents\\Post\\Edit')->write") !== false, 'Registers post write hook');
ap_assert(strpos($pluginSource, "Widget\\Contents\\Page\\Edit')->write") !== false, 'Registers page write hook');
ap_assert(strpos($pluginSource, "Widget\\Contents\\Post\\Edit')->finishPublish") !== false, 'Registers post finishPublish hook');
ap_assert(strpos($pluginSource, "Widget\\Contents\\Page\\Edit')->finishPublish") !== false, 'Registers page finishPublish hook');
ap_assert(strpos($pluginSource, 'function autoInjectProductPanel') !== false, 'Plugin has autoInjectProductPanel');
ap_assert(strpos($pluginSource, 'function findActiveProductByContentId') !== false, 'Plugin can find product by article cid');
ap_assert(strpos($pluginSource, 'function renderProductPanelHtml') !== false, 'Plugin renders article product panel');
ap_assert(strpos($pluginSource, 'function renderArticlePayPanel') !== false, 'Plugin renders article editor panel');
ap_assert(strpos($pluginSource, 'function injectArticleProductShortcode') !== false, 'Plugin can insert product shortcode while saving article');
ap_assert(strpos($pluginSource, 'function saveArticlePaySettings') !== false, 'Plugin saves article pay settings');
ap_assert(strpos($pluginSource, 'function upsertArticleProduct') !== false, 'Plugin upserts product from article cid');
ap_assert(strpos($pluginSource, 'typechopay_pay_mode') !== false, 'Editor panel has pay mode field');
ap_assert(strpos($pluginSource, 'typechopay_insert_shortcode') !== false, 'Editor panel can insert shortcode');
ap_assert(strpos($pluginSource, '$deliverableTargetType') !== false, 'Editor save keeps page/post deliverable target type');
ap_assert(strpos($pluginSource, 'containsTypechoPayShortcode') !== false, 'Auto inject skips manual shortcodes');
ap_assert(strpos($pluginSource, 'typechopay_product(?:\\s+') !== false, 'typechopay_product shortcode supports empty attrs');
ap_assert(strpos($pluginSource, 'product-panel') !== false, 'Theme can override product-panel template');
ap_assert(strpos($pluginSource, 'typechopay-status--') !== false, 'Product panel emits status classes');
ap_assert(strpos($pluginSource, '登录后购买') !== false, 'Product panel exposes login-required state');
ap_assert(strpos($pluginSource, '商品已售罄') !== false, 'Product panel exposes soldout state');

ap_assert(strpos($cssSource, '.typechopay-product-panel') !== false, 'CSS has article product panel styles');
ap_assert(strpos($cssSource, '--typechopay-primary') !== false, 'CSS exposes TypechoPay variables');

echo "\n\n--- ArticleProductTest ---\n";
echo "Passed: {$passed}\n";
echo "Failed: {$failed}\n";
exit($failed > 0 ? 1 : 0);
