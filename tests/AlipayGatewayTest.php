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
alipay_assert(strpos($pluginSource, 'CONFIG_BACKUP_VERSION = 2') !== false, 'Config backup uses versioned format');
alipay_assert(strpos($pluginSource, 'restorePluginConfigFromBackup') !== false, 'Plugin restores backup during activation');
alipay_assert(strpos($pluginSource, 'self::restorePluginConfigFromBackup();') !== false, 'Activation restores plugin config before Typecho renders defaults');
alipay_assert(strpos($pluginSource, 'mergeConfigWithSensitiveFallback') !== false, 'Config defaults preserve backed-up sensitive fields when current values are empty');
alipay_assert(strpos($pluginSource, 'return self::mergeConfigWithSensitiveFallback($backupConfig, $pluginConfig);') !== false, 'Stored config defaults do not let empty plugin rows override sensitive backup fields');
alipay_assert(strpos($pluginSource, '$settings = self::mergeConfigWithSensitiveFallback(self::readConfigBackup(), $settings);') !== false, 'Config backup writer preserves previous sensitive backup values when new settings are empty');
alipay_assert(strpos($pluginSource, 'RedactedHiddenField') !== false, 'Sensitive saved config fields are not rendered back into HTML');
alipay_assert(strpos($pluginSource, "new RedactedHiddenField('alipayPrivateKey', null, '')") !== false, 'Redacted hidden fields use Typecho 1.3 constructor signature');
alipay_assert(strpos($pluginSource, "new RedactedHiddenField('alipayPrivateKey', '')") === false, 'Redacted hidden fields do not pass hidden values as options');
alipay_assert(strpos($pluginSource, 'endpointSecretInput') !== false, 'Endpoint secret replacement uses a separate visible input');
alipay_assert(strpos($pluginSource, 'wechatApiV3KeyInput') !== false, 'WeChat APIv3 key replacement uses a separate visible input');
alipay_assert(strpos($pluginSource, 'alipayPrivateKeyInput') !== false, 'Alipay private key replacement uses a separate visible input');
alipay_assert(strpos($pluginSource, 'alipayPublicKeyInput') !== false, 'Alipay public key replacement uses a separate visible input');
alipay_assert(strpos($pluginSource, 'encryptConfigBackupSecret') !== false, 'Sensitive backup fields are encrypted');
alipay_assert(strpos($pluginSource, 'decryptConfigBackupSecret') !== false, 'Encrypted backup fields can be restored');
alipay_assert(strpos($pluginSource, 'storedConfigDefaults') !== false, 'Plugin restores saved config values into the settings form');
alipay_assert(strpos($pluginSource, "\$settings['alipayAppId'] = trim") !== false, 'Plugin trims Alipay AppID on save');
alipay_assert(strpos($pluginSource, "\$settings['alipaySellerId'] = trim") !== false, 'Plugin trims Alipay Seller ID on save');
alipay_assert(strpos($pluginSource, '不能填到 AppID 字段') !== false, 'Plugin settings distinguish AppID from Seller ID/PID');
alipay_assert(strpos($pluginSource, 'normalizeAlipayPrivateKey') !== false, 'Plugin normalizes Alipay private key PEM text');
alipay_assert(strpos($pluginSource, 'normalizeAlipayPublicKey') !== false, 'Plugin normalizes Alipay public key PEM text');
alipay_assert(strpos($pluginSource, '可以直接粘贴完整 PEM') !== false, 'Plugin settings accept pasted full PEM keys');
alipay_assert(strpos($pluginSource, '插件会保存为 PEM 文本') !== false, 'Plugin settings normalize pasted key bodies to PEM text');
alipay_assert(strpos($pluginSource, 'configSavedLabel') !== false, 'Plugin settings show saved status for redacted sensitive fields');
alipay_assert(strpos($pluginSource, '保存后输入框不会回显') !== false, 'Plugin settings explain why saved Alipay keys render blank inputs');
alipay_assert(strpos($pluginSource, '不是应用公钥') !== false, 'Plugin settings distinguish Alipay private/public keys from the app public key');
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
