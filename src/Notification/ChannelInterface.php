<?php

declare(strict_types=1);

namespace Melo\ExceptionHandler\Notification;

/**
 * 通知渠道：把统一的异常事件转换成对应机器人的消息格式。
 */
interface ChannelInterface
{
    public function name(): string;

    public function send(array $event, array $config, HttpClientInterface $client): void;
}
