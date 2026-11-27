<?php

declare(strict_types=1);

namespace Melo\ExceptionHandler\Notification;

use Throwable;
use Workerman\Timer;

/**
 * 统一通知入口：组装事件、按配置筛选渠道并发送。
 *
 * 所有异常都被吞掉，通知失败不会影响异常响应本身。
 */
class Notifier
{
    /** @var array 通知配置 */
    private array $config;

    private HttpClientInterface $client;

    /** @var array<string, class-string<ChannelInterface>> */
    private array $channels;

    public function __construct(array $config = [], ?HttpClientInterface $client = null, ?array $channels = null)
    {
        $this->config = $config;
        $this->client = $client ?? new HttpClient();
        $this->channels = $channels ?? [
            'dingtalk' => Channel\DingTalkChannel::class,
            'feishu' => Channel\FeishuChannel::class,
            'wecom' => Channel\WeComChannel::class,
        ];
    }

    /**
     * @param mixed $request webman Request，测试时可传入具有相同方法的桩对象
     */
    public function notify(Throwable $e, $request, int $statusCode, string $message): void
    {
        try {
            if (empty($this->config['enable'])) {
                return;
            }

            if ($statusCode < (int)($this->config['min_status'] ?? 500)) {
                return;
            }

            if ($this->isExcluded($e)) {
                return;
            }

            $event = $this->buildEvent($e, $request, $statusCode, $message);

            $async = (bool)($this->config['async'] ?? true);
            if ($async && class_exists(Timer::class)) {
                try {
                    Timer::add(0.001, function () use ($event): void {
                        $this->sendAll($event);
                    });
                    return;
                } catch (Throwable $ignore) {
                    // 无事件循环时退回同步发送
                }
            }

            $this->sendAll($event);
        } catch (Throwable $ignore) {
            // 通知是附加能力，任何失败都不能影响异常响应
        }
    }

    /**
     * 同步发送所有已启用渠道，供子类/测试直接调用。
     */
    protected function sendAll(array $event): void
    {
        foreach ($this->channels as $name => $class) {
            $channelConfig = $this->config['channels'][$name] ?? [];
            if (!is_array($channelConfig) || empty($channelConfig['enable'])) {
                continue;
            }
            if (empty($channelConfig['webhook'])) {
                continue;
            }

            try {
                /** @var ChannelInterface $channel */
                $channel = new $class();
                $channel->send($event, array_merge($channelConfig, [
                    'timeout' => $channelConfig['timeout'] ?? $this->config['timeout'] ?? 2.0,
                    'trace_lines' => $channelConfig['trace_lines'] ?? $this->config['trace_lines'] ?? 5,
                ]), $this->client);
            } catch (Throwable $ignore) {
                // 单个渠道失败不影响其他渠道
            }
        }
    }

    private function isExcluded(Throwable $e): bool
    {
        foreach ((array)($this->config['dont_notify'] ?? []) as $class) {
            if (is_string($class) && $class !== '' && $e instanceof $class) {
                return true;
            }
        }

        return false;
    }

    private function buildEvent(Throwable $e, $request, int $statusCode, string $message): array
    {
        $raw = $e->getMessage();
        $includeRaw = (bool)($this->config['include_raw_message'] ?? true);

        return [
            'time' => date('Y-m-d H:i:s'),
            'environment' => (string)($this->config['environment'] ?? 'prod'),
            'status' => $statusCode,
            'exception' => get_class($e),
            'message' => $message,
            'raw_message' => $includeRaw ? $raw : '',
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => explode("\n", $e->getTraceAsString()),
            'request' => $this->requestInfo($request),
        ];
    }

    private function requestInfo($request): array
    {
        if (!is_object($request)) {
            return [];
        }

        $info = [
            'method' => $this->call($request, 'method'),
            'url' => $this->call($request, 'uri'),
            'host' => $this->call($request, 'host'),
            'ip' => $this->call($request, 'getRealIp') ?: $this->call($request, 'ip'),
            'time' => date('Y-m-d H:i:s'),
        ];

        if (!empty($this->config['include_request_param'])) {
            $all = $this->call($request, 'all');
            if (is_array($all)) {
                $info['param'] = $this->redact($all);
            }
        }

        return array_filter($info, static function ($value) {
            return $value !== null && $value !== '' && $value !== false;
        });
    }

    /**
     * @param mixed $request
     * @return mixed
     */
    private function call($request, string $method)
    {
        return method_exists($request, $method) ? $request->{$method}() : null;
    }

    private function redact(array $data): array
    {
        $keys = array_map('strtolower', (array)($this->config['redact_keys'] ?? [
            'password', 'passwd', 'token', 'secret', 'authorization',
            'access_token', 'refresh_token', 'api_key',
        ]));

        foreach ($data as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), $keys, true)) {
                $data[$key] = '******';
            } elseif (is_array($value)) {
                $data[$key] = $this->redact($value);
            }
        }

        return $data;
    }
}
