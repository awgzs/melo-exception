<?php

declare(strict_types=1);

namespace Melo\ExceptionHandler\Notification;

/**
 * 生成各渠道共用的 Markdown 文本。
 */
class Formatter
{
    /**
     * 按字节截断，避免超出机器人单条消息长度限制。
     */
    public static function truncate(string $text, int $maxBytes = 4000): string
    {
        if (strlen($text) <= $maxBytes) {
            return $text;
        }

        $cut = substr($text, 0, $maxBytes);
        $last = strrpos($cut, "\n");
        if ($last !== false && $last > $maxBytes - 200) {
            $cut = substr($cut, 0, $last);
        }

        return rtrim($cut) . "\n... (内容已截断)";
    }

    public static function markdown(array $event, int $traceLines = 5): string
    {
        $lines = [
            '### 异常告警',
            '**时间：** ' . self::inline($event['time'] ?? ''),
            '**环境：** ' . self::inline($event['environment'] ?? 'prod'),
            '**状态码：** ' . self::inline((string)($event['status'] ?? '')),
            '**异常类：** ' . self::code($event['exception'] ?? ''),
            '**错误消息：** ' . self::inline($event['message'] ?? ''),
        ];

        if (!empty($event['raw_message']) && $event['raw_message'] !== ($event['message'] ?? null)) {
            $lines[] = '**原始错误：** ' . self::inline($event['raw_message']);
        }

        if (!empty($event['file'])) {
            $lines[] = '**位置：** ' . self::code($event['file'] . ':' . ($event['line'] ?? 0));
        }

        $request = $event['request'] ?? [];
        if (!empty($request)) {
            $parts = [];
            if (!empty($request['method']) && !empty($request['url'])) {
                $parts[] = self::inline($request['method'] . ' ' . $request['url']);
            } elseif (!empty($request['method'])) {
                $parts[] = self::inline($request['method']);
            } elseif (!empty($request['url'])) {
                $parts[] = self::inline($request['url']);
            }
            if (!empty($request['ip'])) {
                $parts[] = 'IP ' . self::inline($request['ip']);
            }
            if (!empty($parts)) {
                $lines[] = '**请求：** ' . implode(' | ', $parts);
            }
            if (!empty($request['param'])) {
                $json = json_encode($request['param'], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
                $lines[] = '**请求参数：** ' . self::code(is_string($json) ? $json : '');
            }
        }

        $trace = $event['trace'] ?? [];
        if (!empty($trace)) {
            $lines[] = '**堆栈摘要：**';
            $lines[] = self::block(implode("\n", array_slice($trace, 0, max(1, $traceLines))));
        }

        return implode("\n\n", $lines);
    }

    private static function inline(string $value): string
    {
        return str_replace(["\r", "\n", "\t"], ' ', $value);
    }

    private static function code(string $value): string
    {
        return '`' . str_replace(["\r", "\n"], ' ', $value) . '`';
    }

    private static function block(string $value): string
    {
        return "```\n" . str_replace("\r", '', $value) . "\n```";
    }
}
