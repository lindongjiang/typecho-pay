<?php

use Typecho\Common;

if (!defined('__TYPECHO_ADMIN__')) {
    exit;
}

include 'header.php';
include 'menu.php';

$notifyPayPay = Common::url('/action/typechopay?do=notify&gateway=paypay', $options->index);
$notifyWechat = Common::url('/action/typechopay?do=notify&gateway=wechat', $options->index);
$notifyAlipay = Common::url('/action/typechopay?do=notify&gateway=alipay', $options->index);
$returnPayPay = Common::url('/action/typechopay?do=return&gateway=paypay&out_trade_no={订单号}', $options->index);
$returnAlipay = Common::url('/action/typechopay?do=return&gateway=alipay', $options->index);
?>

<div class="main">
    <div class="body container">
        <?php include 'page-title.php'; ?>

        <div class="table-description">
            <h2>TypechoPay 支付设置说明</h2>
            <p>本页面展示支付网关的回调地址和配置指南。实际配置请在 <a href="<?php echo htmlspecialchars($options->adminUrl); ?>options-plugin.php?config=TypechoPay">插件设置</a> 中填写。</p>
        </div>

        <!-- 回调地址 -->
        <div class="table-description" style="margin-top:30px;">
            <h3>📋 回调地址（Webhook / 异步通知）</h3>
            <p>以下地址需要填写到对应支付平台的回调配置中：</p>

            <table class="typecho-list-table" style="margin-top:15px;">
                <colgroup>
                    <col width="15%">
                    <col width="20%">
                    <col width="65%">
                </colgroup>
                <thead>
                <tr>
                    <th>支付方式</th>
                    <th>用途</th>
                    <th>回调地址</th>
                </tr>
                </thead>
                <tbody>
                <tr>
                    <td><strong>PayPay</strong></td>
                    <td>Webhook 通知</td>
                    <td>
                        <code style="background:#f6f7f8;padding:5px 10px;display:inline-block;margin:5px 0;"><?php echo htmlspecialchars($notifyPayPay); ?></code>
                        <br><small style="color:#888;">在 PayPay 商户后台 → Webhook 设置中填写此 URL</small>
                    </td>
                </tr>
                <tr>
                    <td><strong>微信支付</strong></td>
                    <td>异步通知</td>
                    <td>
                        <code style="background:#f6f7f8;padding:5px 10px;display:inline-block;margin:5px 0;"><?php echo htmlspecialchars($notifyWechat); ?></code>
                        <br><small style="color:#888;">在微信支付商户平台 → 开发配置 → 支付配置 中填写此 URL</small>
                    </td>
                </tr>
                <tr>
                    <td><strong>支付宝</strong></td>
                    <td>异步通知</td>
                    <td>
                        <code style="background:#f6f7f8;padding:5px 10px;display:inline-block;margin:5px 0;"><?php echo htmlspecialchars($notifyAlipay); ?></code>
                        <br><small style="color:#888;">在支付宝开放平台 → 应用配置 → 开发设置 中填写此 URL</small>
                    </td>
                </tr>
                </tbody>
            </table>
        </div>

        <!-- 支付完成回跳地址 -->
        <div class="table-description" style="margin-top:30px;">
            <h3>🔄 支付完成回跳地址</h3>
            <p>支付完成后，部分支付平台会引导用户跳转回此地址：</p>

            <table class="typecho-list-table" style="margin-top:15px;">
                <colgroup>
                    <col width="15%">
                    <col width="65%">
                </colgroup>
                <thead>
                <tr>
                    <th>支付方式</th>
                    <th>回跳地址</th>
                </tr>
                </thead>
                <tbody>
                <tr>
                    <td><strong>PayPay</strong></td>
                    <td>
                        <code style="background:#f6f7f8;padding:5px 10px;display:inline-block;margin:5px 0;"><?php echo htmlspecialchars($returnPayPay); ?></code>
                        <br><small style="color:#888;">系统自动处理，无需手动配置</small>
                    </td>
                </tr>
                <tr>
                    <td><strong>支付宝</strong></td>
                    <td>
                        <code style="background:#f6f7f8;padding:5px 10px;display:inline-block;margin:5px 0;"><?php echo htmlspecialchars($returnAlipay); ?></code>
                        <br><small style="color:#888;">支付宝 Page Pay 模式会使用此地址，系统自动处理</small>
                    </td>
                </tr>
                </tbody>
            </table>
        </div>

        <!-- 配置指南 -->
        <div class="table-description" style="margin-top:30px;">
            <h3>🇯🇵 PayPay 配置指南</h3>
            <p>PayPay 是日本主流移动支付，当前插件使用 PayPay Open Payment API 的 Dynamic QR。</p>
            <ol>
                <li>请通过 PayPay 官方加盟店/开发者流程申请 OPA 权限，并获取 <strong>API Key</strong>、<strong>API Secret</strong> 和 <strong>Merchant ID</strong></li>
                <li>参考 <a href="https://www.paypay.ne.jp/opa/doc/jp/v1.0/dynamicqrcode" target="_blank">PayPay Dynamic QR 文档</a> 配置 Dynamic QR 支付</li>
                <li>设置 Webhook URL 为上方的 PayPay 回调地址</li>
                <li>在插件设置中填写以上信息，环境选择 <strong>Sandbox</strong> 进行测试</li>
                <li>测试完成后切换到 <strong>Production</strong> 环境</li>
            </ol>
            <p><strong>注意事项：</strong></p>
            <ul>
                <li>PayPay 只支持 JPY（日元），金额为整数</li>
                <li>Sandbox 环境用于开发测试，不会产生真实交易</li>
            </ul>
        </div>

        <div class="table-description" style="margin-top:30px;">
            <h3>💚 微信支付配置指南</h3>
            <ol>
                <li>访问 <a href="https://pay.weixin.qq.com/" target="_blank">微信支付商户平台</a>，确保已开通 Native 支付</li>
                <li>在 <strong>API 安全</strong> 页面：
                    <ul>
                        <li>下载 <strong>商户 API 证书</strong>（apiclient_cert.pem、apiclient_key.pem）</li>
                        <li>记录 <strong>证书序列号</strong></li>
                        <li>设置 <strong>APIv3 Key</strong>（32 位密钥）</li>
                    </ul>
                </li>
                <li>在 <strong>API 安全</strong> 页面下载 <strong>微信支付平台证书</strong>（用于回调验签）</li>
                <li>在插件设置中填写 AppID、商户号、证书序列号、私钥路径、APIv3 Key 等</li>
                <li>在微信支付商户平台 → 开发配置 → 支付配置 中填写上方的回调地址</li>
            </ol>
            <p><strong>注意事项：</strong></p>
            <ul>
                <li>私钥文件（apiclient_key.pem）请放在网站目录外，确保 PHP 有读取权限</li>
                <li>APIv3 Key 用于解密回调通知，务必正确设置</li>
                <li>微信支付只支持 CNY（人民币），金额单位为分</li>
            </ul>
        </div>

        <div class="table-description" style="margin-top:30px;">
            <h3>🔵 支付宝配置指南</h3>
            <ol>
                <li>访问 <a href="https://open.alipay.com/" target="_blank">支付宝开放平台</a> 创建应用</li>
                <li>在应用详情中开通 <strong>电脑网站支付</strong> 或 <strong>当面付</strong> 能力</li>
                <li>在 <strong>开发设置 → 接口加签方式</strong> 中：
                    <ul>
                        <li>选择 <strong>普通公钥模式</strong></li>
                        <li>使用支付宝密钥生成工具生成 <strong>RSA2 密钥对</strong></li>
                        <li>上传应用公钥，获取 <strong>支付宝公钥</strong></li>
                    </ul>
                </li>
                <li>在插件设置中填写 AppID、应用私钥、支付宝公钥</li>
                <li>在支付宝开放平台 → 应用配置 → 开发设置 中填写上方的异步通知地址</li>
            </ol>
            <p><strong>证书模式：</strong>当前插件暂不支持支付宝公钥证书模式。如果你的应用已经使用公钥证书模式，请先改用普通公钥模式，或后续扩展证书字段。</p>
            <p><strong>注意事项：</strong></p>
            <ul>
                <li><strong>应用私钥</strong>是敏感信息，请勿截图外泄或提交到代码仓库</li>
                <li><strong>支付宝公钥</strong>不是应用公钥，注意区分</li>
                <li>Page Pay 模式会跳转支付宝收银台，Precreate 模式生成二维码</li>
                <li>支付宝只支持 CNY（人民币）。插件短代码中的 CNY 金额单位为“分”，插件内部会在调用支付宝接口时转换为“元”。</li>
            </ul>
        </div>

        <!-- 短代码使用说明 -->
        <div class="table-description" style="margin-top:30px;">
            <h3>📝 短代码使用说明</h3>
            <p>在文章中插入以下短代码创建支付入口：</p>

            <h4 style="margin-top:15px;">基础支付按钮</h4>
            <pre style="background:#f6f7f8;padding:15px;border:1px solid #ddd;overflow-x:auto;"><code>[typechopay amount="500" currency="JPY" subject="商品名称" gateways="paypay"]</code></pre>
            <p><strong>参数说明：</strong></p>
            <ul>
                <li><code>amount</code>：金额，<strong>最小货币单位</strong>（JPY 为日元整数，CNY 为分）</li>
                <li><code>currency</code>：币种（JPY 或 CNY），可选，默认使用插件设置的默认币种</li>
                <li><code>subject</code>：商品/订单标题，可选，默认使用文章标题</li>
                <li><code>gateways</code>：指定支付方式（paypay/wechat/alipay），多个用逗号分隔，可选</li>
            </ul>

            <h4 style="margin-top:15px;">付费阅读内容</h4>
            <pre style="background:#f6f7f8;padding:15px;border:1px solid #ddd;overflow-x:auto;"><code>[typechopay_content]
这里是购买后才能看到的隐藏内容。
[/typechopay_content]</code></pre>
            <p>也可以绑定到指定业务对象：</p>
            <pre style="background:#f6f7f8;padding:15px;border:1px solid #ddd;overflow-x:auto;"><code>[typechopay_content biz_type="post" biz_id="123"]
隐藏内容...
[/typechopay_content]</code></pre>
        </div>

        <!-- 常见问题 -->
        <div class="table-description" style="margin-top:30px;">
            <h3>❓ 常见问题</h3>

            <h4 style="margin-top:15px;">Q: 支付成功但内容没有解锁？</h4>
            <p>A: 请在后台"支付订单"页面找到对应订单，点击"重发权益"按钮。如果仍然失败，请检查 PHP 错误日志。</p>

            <h4 style="margin-top:15px;">Q: 微信支付回调一直失败？</h4>
            <p>A: 请检查：</p>
            <ul>
                <li>回调地址是否正确填写到微信支付商户平台</li>
                <li>APIv3 Key 是否正确设置</li>
                <li>平台证书路径是否正确，PHP 是否有读取权限</li>
                <li>服务器是否支持 HTTPS（微信支付要求回调地址为 HTTPS）</li>
            </ul>

            <h4 style="margin-top:15px;">Q: 如何切换支付环境？</h4>
            <p>A: PayPay 可以在插件设置中切换 Sandbox/Staging/Production 环境。微信和支付宝没有环境切换，使用各自的测试/生产账号即可。</p>

            <h4 style="margin-top:15px;">Q: 金额单位是什么？</h4>
            <p>A: </p>
            <ul>
                <li><strong>JPY（日元）：</strong>最小单位是日元整数，<code>amount="500"</code> 表示 500 日元</li>
                <li><strong>CNY（人民币）：</strong>最小单位是分，<code>amount="500"</code> 表示 5.00 元</li>
            </ul>
        </div>

        <div style="margin-top:40px;padding:20px;background:#f9f9f9;border:1px solid #ddd;">
            <p><strong>需要更多帮助？</strong>请查看插件目录下的 <code>README.md</code> 文件或 <code>docs/</code> 目录下的文档。</p>
        </div>

    </div>
</div>

<?php include 'footer.php'; ?>
