<?php

declare(strict_types=1);

namespace Melo\ExceptionHandler\Notification;

/**
 * 无第三方依赖的 HTTP 客户端：优先使用 curl，缺失时用 PHP 流上下文。
 */
class HttpClient implements HttpClientInterface
{
    public function post(string $url, array $payload, float $timeout = 2.0): void
    {
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($body === false) {
            return;
        }

        $timeout = $timeout > 0 ? $timeout : 2.0;

        if (function_exists('curl_init')) {
            $this->postWithCurl($url, $body, $timeout);
            return;
        }

        $this->postWithStream($url, $body, $timeout);
    }

    private function postWithCurl(string $url, string $body, float $timeout): void
    {
        $ch = curl_init($url);
        if ($ch === false) {
            return;
        }

        $timeoutMs = (int)ceil($timeout * 1000);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_CONNECTTIMEOUT_MS => $timeoutMs,
            CURLOPT_TIMEOUT_MS => $timeoutMs,
        ]);

        @curl_exec($ch);
        curl_close($ch);
    }

    private function postWithStream(string $url, string $body, float $timeout): void
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n",
                'content' => $body,
                'timeout' => $timeout,
                'ignore_errors' => true,
            ],
        ]);

        @file_get_contents($url, false, $context);
    }
}
