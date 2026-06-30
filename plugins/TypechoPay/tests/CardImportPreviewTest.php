<?php

/**
 * Static regression checks for card import preview/confirm flow.
 */

$root = dirname(__DIR__);
$passed = 0;
$failed = 0;

function cip_assert(bool $condition, string $label): void
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

$productsSource = file_get_contents($root . '/manage/products.php');

cip_assert(strpos($productsSource, 'typechopay_write_import_preview') !== false, 'Writes import preview to temp storage');
cip_assert(strpos($productsSource, 'typechopay_read_import_preview') !== false, 'Reads import preview by token on confirm');
cip_assert(strpos($productsSource, "'preview_token' => \$previewToken") !== false, 'Preview data carries token');
cip_assert(strpos($productsSource, 'name="preview_token"') !== false, 'Confirm form submits preview token');
cip_assert(strpos($productsSource, '$shouldRedirect = false') !== false, 'Preview action does not redirect');
cip_assert(strpos($productsSource, 'name="card_lines" style="display:none;"') === false, 'Confirm form does not embed raw card lines');

echo "\n\n--- CardImportPreviewTest ---\n";
echo "Passed: {$passed}\n";
echo "Failed: {$failed}\n";
exit($failed > 0 ? 1 : 0);
