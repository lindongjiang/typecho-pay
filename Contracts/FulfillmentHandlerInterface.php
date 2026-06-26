<?php

namespace TypechoPlugin\TypechoPay\Contracts;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

interface FulfillmentHandlerInterface
{
    public function key(): string;

    public function validate(array $product, array $deliverable, array $buyer): void;

    public function reserve(array $order, array $deliverable): void;

    public function fulfill(array $order, array $deliverable): array;

    public function release(array $order, array $deliverable): void;

    public function revoke(array $order, array $deliverable): void;
}
