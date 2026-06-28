<?php

/**
 * Static regression checks for Alipay sandbox gateway configuration.
 */

$root = dirname(__DIR__);
$passed = 0;
$failed = 0;

function alipay_assert(bool $condition, string $label): void
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
$gatewaySource = file_get_contents($root . '/Gateways/AlipayGateway.php');
$sdkSource = file_get_contents($root . '/Support/AlipaySdk.php');
$settingsHelpSource = file_get_contents($root . '/manage/settings-help.php');

alipay_assert(strpos($pluginSource, 'alipayGatewayUrl') !== false, 'Plugin config exposes Alipay gateway URL');
alipay_assert(strpos($pluginSource, 'normalizeAlipayGatewayUrl') !== false, 'Plugin normalizes Alipay gateway URL');
alipay_assert(strpos($pluginSource, 'configHandle(array $settings') !== false, 'Plugin owns config saving for normalization and backup');
alipay_assert(strpos($pluginSource, 'CONFIG_BACKUP_OPTION') !== false, 'Plugin backs up settings across disable/enable update cycles');
alipay_assert(strpos($pluginSource, 'storedConfigDefaults') !== false, 'Plugin restores saved config values into the settings form');
alipay_assert(strpos($pluginSource, 'normalizeAlipayPrivateKey') !== false, 'Plugin normalizes Alipay private key PEM text');
alipay_assert(strpos($pluginSource, 'normalizeAlipayPublicKey') !== false, 'Plugin normalizes Alipay public key PEM text');
alipay_assert(strpos($pluginSource, '可以直接粘贴完整 PEM') !== false, 'Plugin settings accept pasted full PEM keys');
alipay_assert(strpos($pluginSource, '插件会保存为 PEM 文本') !== false, 'Plugin settings normalize pasted key bodies to PEM text');
alipay_assert(strpos($pluginSource, 'openapi-sandbox.dl.alipaydev.com/gateway.do') !== false, 'Plugin mentions Alipay sandbox gateway URL');
alipay_assert(strpos($gatewaySource, '$client->gatewayUrl = $this->gatewayUrl();') !== false, 'Alipay client uses configured gateway URL');
alipay_assert(strpos($gatewaySource, 'function gatewayUrl') !== false, 'Alipay gateway has URL normalization fallback');
alipay_assert(strpos($gatewaySource, "\$client->gatewayUrl = 'https://openapi.alipay.com/gateway.do';") === false, 'Alipay client no longer hardcodes production gateway');
alipay_assert(strpos($sdkSource, "dirname(__DIR__) . '/vendor/alipaysdk/openapi/v2/aop'") !== false, 'Alipay SDK loader resolves vendor from plugin root');
alipay_assert(strpos($sdkSource, "dirname(__DIR__, 2) . '/vendor/alipaysdk") === false, 'Alipay SDK loader does not skip past plugin root');
alipay_assert(strpos($settingsHelpSource, 'openapi-sandbox.dl.alipaydev.com/gateway.do') !== false, 'Settings help documents sandbox gateway URL');

echo "\n\n--- AlipayGatewayTest ---\n";
echo "Passed: {$passed}\n";
echo "Failed: {$failed}\n";
exit($failed > 0 ? 1 : 0);
