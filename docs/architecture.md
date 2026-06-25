# TypechoPay Architecture

## 目标

Typecho 只承担订单中心职责：

- 渲染可信支付入口
- 创建订单
- 调用网关创建支付会话
- 接收并验签异步通知
- Webhook 失败时主动查单补状态
- 幂等更新订单状态
- 保留事件审计
- 发放最小付费阅读权益

支付渠道被隔离在 `src/Gateways` 下，业务层不直接依赖某个支付 SDK。

## 请求路径

```mermaid
flowchart LR
  A["文章短代码"] --> B["HMAC 签名付款入口"]
  B --> C["/action/typechopay?do=create"]
  C --> N["pay_nonces consume"]
  N --> D["pay_orders pending"]
  D --> E["Gateway create"]
  E --> F["用户支付"]
  F --> G["/action/typechopay?do=notify"]
  G --> H["验签/解密/金额校验"]
  H --> I["pay_events"]
  H --> J["pay_orders paid"]
  J --> K["pay_entitlements"]
```

## 关键表

`pay_orders` 是订单事实表。`out_trade_no` 是商户侧唯一订单号，支付平台交易号只写入 `platform_trade_no`。

`pay_events` 是通知/主动查单事件表。即使通知失败，也保留事件类型、签名结果、provider event id/type、平台交易号、远端 IP、请求头和 payload 摘要，方便排查支付平台重试。

`pay_entitlements` 是最小权益表。订单进入 `paid` 后由 `AccessService::grant()` 写入，用于付费阅读内容判断。

`pay_nonces` 是短代码支付入口的一次性 nonce 表。创建订单前先验签完整支付意图，再写入 nonce 哈希；重复提交同一个入口会被拒绝。

## 网关契约

每个网关实现：

- `create(array $order): PayCreateResult`
- `notify(array $headers, string $rawBody, array $query, array $post): NotifyResult`
- `query(array $order): NotifyResult`

网关只负责和支付平台通信、验签、把平台状态转换成统一结果。订单写入和状态流转只在 `OrderService` 中完成。

## 当前边界

此版本仍不是卡密/库存交付系统。已支付后只发放 `post` 付费阅读权益；下载资源、VIP、余额、积分、优惠码和退款状态仍需要独立业务处理器扩展。
