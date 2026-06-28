<?php

use Typecho\Common;
use TypechoPlugin\TypechoPay\Plugin;
use TypechoPlugin\TypechoPay\Support\AlipaySdk;

if (!defined('__TYPECHO_ADMIN__')) {
    exit;
}

$config = Plugin::pluginConfig($options);
$notifyWechat = Common::url('/action/typechopay?do=notify&gateway=wechat', $options->index);
$notifyAlipay = Common::url('/action/typechopay?do=notify&gateway=alipay', $options->index);

$rows = [];
$addCheck = static function (string $group, string $item, bool $ok, string $message, string $level = '') use (&$rows): void {
    $rows[] = [
        'group' => $group,
        'item' => $item,
        'ok' => $ok,
        'level' => $level !== '' ? $level : ($ok ? 'ok' : 'fail'),
        'message' => $message,
    ];
};
$hasValue = static fn($value): bool => trim((string) $value) !== '';
$isHttps = static fn(string $url): bool => strtolower((string) parse_url($url, PHP_URL_SCHEME)) === 'https';

$addCheck('通用', 'PHP cURL 扩展', extension_loaded('curl'), extension_loaded('curl') ? '已安装' : '未安装，支付平台 HTTP 请求会失败');
$addCheck('通用', 'PHP OpenSSL 扩展', extension_loaded('openssl'), extension_loaded('openssl') ? '已安装' : '未安装，验签、加密和密钥解析会失败');
$addCheck('通用', 'PHP JSON 扩展', extension_loaded('json'), extension_loaded('json') ? '已安装' : '未安装，支付接口 JSON 处理会失败');
$addCheck('通用', 'Composer autoload', is_file(dirname(__DIR__) . '/vendor/autoload.php'), is_file(dirname(__DIR__) . '/vendor/autoload.php') ? 'vendor/autoload.php 存在' : '请先执行 composer install --no-dev');
$addCheck('通用', '微信支付 SDK', class_exists('\\WeChatPay\\Builder'), class_exists('\\WeChatPay\\Builder') ? 'wechatpay/wechatpay 可加载' : '未加载，请检查 composer 依赖');
try {
    AlipaySdk::ensureAop();
    $addCheck('通用', '支付宝 AOP SDK', class_exists('\\AopClient', false), 'alipaysdk/openapi 可加载');
} catch (Throwable $e) {
    $addCheck('通用', '支付宝 AOP SDK', false, $e->getMessage());
}

$addCheck('微信支付', 'AppID', $hasValue($config['wechatAppId'] ?? ''), $hasValue($config['wechatAppId'] ?? '') ? '已填写' : '未填写');
$addCheck('微信支付', '商户号 MchID', $hasValue($config['wechatMchId'] ?? ''), $hasValue($config['wechatMchId'] ?? '') ? '已填写' : '未填写');
$wechatPrivateKeyPath = (string) ($config['wechatPrivateKeyPath'] ?? '');
$addCheck('微信支付', '商户私钥路径', $wechatPrivateKeyPath !== '' && is_file($wechatPrivateKeyPath) && is_readable($wechatPrivateKeyPath), $wechatPrivateKeyPath === '' ? '未填写' : (is_readable($wechatPrivateKeyPath) ? '文件存在且 PHP 可读' : '文件不存在或 PHP 不可读'));
$wechatPlatformPath = (string) ($config['wechatPlatformPublicKeyPath'] ?? '');
$addCheck('微信支付', '平台公钥/证书路径', $wechatPlatformPath !== '' && is_file($wechatPlatformPath) && is_readable($wechatPlatformPath), $wechatPlatformPath === '' ? '未填写' : (is_readable($wechatPlatformPath) ? '文件存在且 PHP 可读' : '文件不存在或 PHP 不可读'));
$apiV3Key = (string) ($config['wechatApiV3Key'] ?? '');
$addCheck('微信支付', 'APIv3 Key', strlen($apiV3Key) === 32, $apiV3Key === '' ? '未填写' : (strlen($apiV3Key) === 32 ? '长度为 32 位' : '长度不是 32 位'));
$addCheck('微信支付', '异步通知 HTTPS', $isHttps($notifyWechat), $notifyWechat);

$addCheck('支付宝', 'AppID', $hasValue($config['alipayAppId'] ?? ''), $hasValue($config['alipayAppId'] ?? '') ? '已填写' : '未填写');
$gatewayUrl = (string) ($config['alipayGatewayUrl'] ?? '');
$addCheck('支付宝', '网关地址 HTTPS', $gatewayUrl !== '' && $isHttps($gatewayUrl), $gatewayUrl !== '' ? $gatewayUrl : '未填写');
$privateKey = (string) ($config['alipayPrivateKey'] ?? '');
$privateOk = function_exists('openssl_pkey_get_private') && $privateKey !== '' && @openssl_pkey_get_private($privateKey) !== false;
$addCheck('支付宝', '应用私钥格式', $privateOk, $privateKey === '' ? '未填写' : ($privateOk ? 'OpenSSL 可解析' : 'OpenSSL 无法解析，请确认是应用私钥'));
$publicKey = (string) ($config['alipayPublicKey'] ?? '');
$publicOk = function_exists('openssl_pkey_get_public') && $publicKey !== '' && @openssl_pkey_get_public($publicKey) !== false;
$addCheck('支付宝', '支付宝公钥格式', $publicOk, $publicKey === '' ? '未填写' : ($publicOk ? 'OpenSSL 可解析' : 'OpenSSL 无法解析，请确认填写的是支付宝公钥，不是应用公钥'));
$addCheck('支付宝', '异步通知 HTTPS', $isHttps($notifyAlipay), $notifyAlipay);

include 'header.php';
include 'menu.php';
?>

<div class="main">
    <div class="body container">
        <?php include 'page-title.php'; ?>

        <div class="table-description">
            <h2>TypechoPay 支付诊断</h2>
            <p>这里只检查环境、依赖、回调地址和关键配置是否可用，不会发起真实支付请求，也不会输出任何密钥明文。</p>
        </div>

        <div class="typecho-table-wrap">
            <table class="typecho-list-table">
                <thead>
                <tr>
                    <th>分组</th>
                    <th>检查项</th>
                    <th>状态</th>
                    <th>说明</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <?php
                    $color = $row['level'] === 'ok' ? '#10b981' : ($row['level'] === 'warn' ? '#f59e0b' : '#ef4444');
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['group']); ?></td>
                        <td><?php echo htmlspecialchars($row['item']); ?></td>
                        <td><strong style="color:<?php echo $color; ?>"><?php echo $row['ok'] ? '通过' : '需处理'; ?></strong></td>
                        <td><?php echo htmlspecialchars($row['message']); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'copyright.php'; ?>
