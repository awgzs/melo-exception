<?php

declare(strict_types=1);

namespace Melo\ExceptionHandler\Notification\Channel;

use Melo\ExceptionHandler\Notification\ChannelInterface;
use Melo\ExceptionHandler\Notification\Formatter;
use Melo\ExceptionHandler\Notification\HttpClientInterface;

/**
 * 钉钉自定义机器人：支持 webhook 加签。
 */
class DingTalkChannel implements ChannelInterface
{
    public function name(): string
    {
        return 'dingtalk';
    }

    public function send(array $event, array $config, HttpClientInterface $client): void
    {
        $url = (string)($config['webhook'] ?? '');
        if ($url === '') {
            return;
        }

        $secret = (string)($config['secret'] ?? '');
        if ($secret !== '') {
            $timestamp = (string)((int)(microtime(true) * 1000));
            $sign = base64_encode(hash_hmac('sha256', $timestamp . "\n" . $secret, $secret, true));
            $separator = str_contains($url, '?') ? '&' : '?';
            $url .= $separator . 'timestamp=' . $timestamp . '&sign=' . rawurlencode($sign);
        }

        $text = Formatter::markdown($event, (int)($config['trace_lines'] ?? 5));
        $client->post($url, [
            'msgtype' => 'markdown',
            'markdown' => [
                'title' => '异常告警',
                'text' => Formatter::truncate($text),
            ],
        ], (float)($config['timeout'] ?? 2.0));
    }
}
