<?php

declare(strict_types=1);

namespace Melo\ExceptionHandler\Notification\Channel;

use Melo\ExceptionHandler\Notification\ChannelInterface;
use Melo\ExceptionHandler\Notification\Formatter;
use Melo\ExceptionHandler\Notification\HttpClientInterface;

/**
 * 飞书自定义机器人：支持签名校验。
 */
class FeishuChannel implements ChannelInterface
{
    public function name(): string
    {
        return 'feishu';
    }

    public function send(array $event, array $config, HttpClientInterface $client): void
    {
        $url = (string)($config['webhook'] ?? '');
        if ($url === '') {
            return;
        }

        $payload = [
            'msg_type' => 'text',
            'content' => [
                'text' => Formatter::truncate(
                    Formatter::markdown($event, (int)($config['trace_lines'] ?? 5))
                ),
            ],
        ];

        $secret = (string)($config['secret'] ?? '');
        if ($secret !== '') {
            $timestamp = (string)time();
            $payload['timestamp'] = $timestamp;
            $payload['sign'] = base64_encode(
                hash_hmac('sha256', $timestamp . "\n" . $secret, $secret, true)
            );
        }

        $client->post($url, $payload, (float)($config['timeout'] ?? 2.0));
    }
}
