<?php

/**
 * Static regression checks for full card-code display in admin surfaces.
 */

$root = dirname(__DIR__);
$passed = 0;
$failed = 0;

function cad_assert(bool $condition, string $label): void
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

$serviceSource = file_get_contents($root . '/Services/CardCodeService.php');
$pluginSource = file_get_contents($root . '/Plugin.php');
$inventorySource = file_get_contents($root . '/manage/card-inventory.php');
$salesSource = file_get_contents($root . '/manage/card-sales.php');

cad_assert(strpos($serviceSource, 'withAdminDisplayField') !== false, 'Card service prepares admin display fields');
cad_assert(strpos($serviceSource, 'card_display') !== false, 'Card service exposes full card display value');
cad_assert(strpos($serviceSource, "'code_mask' => null") !== false, 'New imports no longer store card code text in code_mask');
cad_assert(strpos($serviceSource, 'legacyCodeLabel') === false, 'Card service no longer generates legacy card labels');
cad_assert(strpos($serviceSource, " . '****' . ") === false, 'Card service no longer generates starred masks for new rows');
cad_assert(strpos($pluginSource, 'card_display') !== false, 'Article editor recent card table uses full card display');
cad_assert(strpos($inventorySource, 'card_display') !== false, 'Inventory page uses full card display');
cad_assert(strpos($inventorySource, '卡号掩码') === false, 'Inventory page no longer labels cards as masked');
cad_assert(strpos($salesSource, 'card_display') !== false, 'Sales page shows full delivered card display');
cad_assert(strpos($inventorySource, 'Cache-Control') !== false, 'Inventory page sends no-store cache header');
cad_assert(strpos($salesSource, 'Cache-Control') !== false, 'Sales page sends no-store cache header');

echo "\n\n--- CardAdminDisplayTest ---\n";
echo "Passed: {$passed}\n";
echo "Failed: {$failed}\n";
exit($failed > 0 ? 1 : 0);
