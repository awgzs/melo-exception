<?php

declare(strict_types=1);

namespace Melo\ExceptionHandler\Notification;

/**
 * 通知渠道使用的极简 HTTP 客户端，便于测试时替换。
 */
interface HttpClientInterface
{
    /**
     * 发送 JSON POST 请求，实现方不应抛出异常。
     */
    public function post(string $url, array $payload, float $timeout = 2.0): void;
}
