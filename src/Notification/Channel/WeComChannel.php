<?php

declare(strict_types=1);

namespace Melo\ExceptionHandler\Notification\Channel;

use Melo\ExceptionHandler\Notification\ChannelInterface;
use Melo\ExceptionHandler\Notification\Formatter;
use Melo\ExceptionHandler\Notification\HttpClientInterface;

/**
 * 企业微信机器人：markdown 消息。
 */
class WeComChannel implements ChannelInterface
{
    public function name(): string
    {
        return 'wecom';
    }

    public function send(array $event, array $config, HttpClientInterface $client): void
    {
        $url = (string)($config['webhook'] ?? '');
        if ($url === '') {
            return;
        }

        $text = Formatter::truncate(
            Formatter::markdown($event, (int)($config['trace_lines'] ?? 5)),
            4096
        );

        $client->post($url, [
            'msgtype' => 'markdown',
            'markdown' => ['content' => $text],
        ], (float)($config['timeout'] ?? 2.0));
    }
}
