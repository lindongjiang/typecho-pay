<?php

use Typecho\Common;

if (!defined('__TYPECHO_ADMIN__')) {
    exit;
}

include 'header.php';
include 'menu.php';

$notifyWechat = Common::url('/action/typechopay?do=notify&gateway=wechat', $options->index);
$notifyAlipay = Common::url('/action/typechopay?do=notify&gateway=alipay', $options->index);
$returnAlipay = Common::url('/action/typechopay?do=return&gateway=alipay', $options->index);
?>

<div class="main">
    <div class="body container">
        <?php include 'page-title.php'; ?>

        <div class="table-description">
            <h2>TypechoPay 支付设置说明</h2>
            <p>当前插件后台只保留人民币支付。文章和商品表单中的金额按“元”填写，最低 0.01 元；插件内部会按分保存并传给支付平台。</p>
            <p>实际商户参数请在 <a href="<?php echo htmlspecialchars($options->adminUrl); ?>options-plugin.php?config=TypechoPay">插件设置</a> 中填写。</p>
        </div>

        <div class="table-description" style="margin-top:30px;">
            <h3>回调地址</h3>
            <table class="typecho-list-table" style="margin-top:15px;">
                <thead>
                <tr>
                    <th>支付方式</th>
                    <th>用途</th>
                    <th>地址</th>
                </tr>
                </thead>
                <tbody>
                <tr>
                    <td><strong>微信支付</strong></td>
                    <td>异步通知</td>
                    <td>
                        <code style="background:#f6f7f8;padding:5px 10px;display:inline-block;margin:5px 0;"><?php echo htmlspecialchars($notifyWechat); ?></code>
                        <br><small style="color:#888;">填写到微信支付商户平台的支付通知配置中。</small>
                    </td>
                </tr>
                <tr>
                    <td><strong>支付宝</strong></td>
                    <td>异步通知</td>
                    <td>
                        <code style="background:#f6f7f8;padding:5px 10px;display:inline-block;margin:5px 0;"><?php echo htmlspecialchars($notifyAlipay); ?></code>
                        <br><small style="color:#888;">填写到支付宝开放平台应用的异步通知地址中。</small>
                    </td>
                </tr>
                <tr>
                    <td><strong>支付宝</strong></td>
                    <td>同步回跳</td>
                    <td>
                        <code style="background:#f6f7f8;padding:5px 10px;display:inline-block;margin:5px 0;"><?php echo htmlspecialchars($returnAlipay); ?></code>
                        <br><small style="color:#888;">Page Pay 模式使用，通常无需手动填写。</small>
                    </td>
                </tr>
                </tbody>
            </table>
        </div>

        <div class="table-description" style="margin-top:30px;">
            <h3>支付宝配置</h3>
            <ol>
                <li>在 <a href="https://open.alipay.com/" target="_blank">支付宝开放平台</a> 创建应用，并开通电脑网站支付和手机网站支付。</li>
                <li>接口加签方式选择 <strong>普通公钥模式</strong>，生成应用私钥，上传应用公钥后复制支付宝公钥。</li>
                <li>插件设置中填写 AppID、网关地址、应用私钥、支付宝公钥和可选 Seller ID；AppID 是应用详情里的 APPID，不是绑定商家账号 PID。应用私钥和支付宝公钥直接粘贴文本保存，不需要上传密钥文件。</li>
                <li>沙箱测试网关：<code>https://openapi-sandbox.dl.alipaydev.com/gateway.do</code>；正式环境网关：<code>https://openapi.alipay.com/gateway.do</code>。</li>
            </ol>
            <p><strong>注意：</strong>当前暂不支持支付宝公钥证书模式。请使用普通公钥模式，或后续再扩展证书字段。电脑浏览器会进入支付宝电脑网站支付 Page Pay 收银台；手机浏览器会进入支付宝手机网站支付 H5。插件保存设置时会把只粘贴正文的密钥规范化为 PEM 文本；支付宝应用私钥和支付宝公钥会按当前配置正常显示在输入框中。升级或停用再启用时会从加密配置备份恢复。</p>
        </div>

        <div class="table-description" style="margin-top:30px;">
            <h3>微信支付配置</h3>
            <ol>
                <li>确认微信支付商户已开通 Native 扫码支付。</li>
                <li>在 API 安全页面准备商户 API 私钥、证书序列号、平台公钥/证书和 APIv3 Key。</li>
                <li>插件设置中填写 AppID、商户号、证书序列号、私钥路径、平台公钥/证书路径和 APIv3 Key。</li>
                <li>把上方微信异步通知地址填写到微信支付商户平台。</li>
            </ol>
            <p><strong>注意：</strong>私钥文件建议放在网站目录外，并确认 PHP 进程有读取权限。</p>
        </div>

        <div class="table-description" style="margin-top:30px;">
            <h3>文章卡密流程</h3>
            <ol>
                <li>编辑文章，在底部“文章付费与卡密”中选择“卡密管理”。</li>
                <li>填写价格（元）、购买权限和商品标题，保存文章后自动创建绑定商品。</li>
                <li>在“卡密列表 / 添加卡密”页签中查看库存或粘贴卡密，保存文章即可导入。</li>
                <li>需要批量预览、文件导入、作废或销售查询时，再进入“商品与卡密 / 卡密库存 / 卡密销售”。</li>
            </ol>
        </div>

        <div class="table-description" style="margin-top:30px;">
            <h3>短代码</h3>
            <p>推荐使用商品模式，价格和交付规则始终从服务端商品读取：</p>
            <pre style="background:#f6f7f8;padding:15px;border:1px solid #ddd;overflow-x:auto;"><code>[typechopay_product]</code></pre>
            <pre style="background:#f6f7f8;padding:15px;border:1px solid #ddd;overflow-x:auto;"><code>[typechopay product="article-123-premium" gateways="alipay"]</code></pre>
            <pre style="background:#f6f7f8;padding:15px;border:1px solid #ddd;overflow-x:auto;"><code>[typechopay product_id="18" gateways="wechat,alipay"]</code></pre>

            <p>旧版金额短代码仍保留兼容。这里的 <code>amount</code> 仍按“分”填写：</p>
            <pre style="background:#f6f7f8;padding:15px;border:1px solid #ddd;overflow-x:auto;"><code>[typechopay amount="500" currency="CNY" subject="商品名称" gateways="alipay"]</code></pre>
            <p><code>amount="500"</code> 表示 5.00 元。新商品建议使用文章底部面板或商品模式，不建议继续把价格写死在文章正文里。</p>

            <h4 style="margin-top:15px;">付费阅读内容</h4>
            <pre style="background:#f6f7f8;padding:15px;border:1px solid #ddd;overflow-x:auto;"><code>[typechopay_content]
这里是购买后才能看到的隐藏内容。
[/typechopay_content]</code></pre>
        </div>

        <div class="table-description" style="margin-top:30px;">
            <h3>常见问题</h3>
            <h4>支付成功但内容没有解锁？</h4>
            <p>到“支付订单”找到对应订单，点击“重发交付”。仍失败时再检查 PHP 错误日志。</p>

            <h4>支付成功但卡密没有发出？</h4>
            <p>先补充库存，再到“支付订单”重发交付。重复回调或重复重发不会重复发同一张已交付卡密。</p>

            <h4>金额应该怎么填？</h4>
            <p>后台文章和商品表单按“元”填写，最低 0.01 元。旧版金额短代码按“分”填写，仅作为兼容层保留。</p>

            <h4>升级后看不到新面板？</h4>
            <p>请在 Typecho 后台停用并重新启用 TypechoPay，让插件执行 schema 迁移并刷新后台菜单。</p>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
