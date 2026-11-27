<?php

declare(strict_types=1);

namespace Workerman {
    class Timer
    {
        /** @var array<int, callable> */
        public static array $callbacks = [];

        public static function add(float $delay, callable $callback, ...$args)
        {
            self::$callbacks[] = $callback;
            return count(self::$callbacks);
        }

        public static function runAll(): void
        {
            $callbacks = self::$callbacks;
            self::$callbacks = [];
            foreach ($callbacks as $callback) {
                $callback();
            }
        }
    }
}

namespace Webman\Exception {
    class ExceptionHandler
    {
        public $dontReport = [];
        protected $logger;
        protected $debug;

        public function __construct($logger, $debug)
        {
            $this->logger = $logger;
            $this->debug = $debug;
        }

        public function report(\Throwable $exception)
        {
            if ($this->shouldntReport($exception)) {
                return;
            }
            $this->logger->error((string)$exception);
        }

        protected function shouldntReport(\Throwable $e): bool
        {
            foreach ($this->dontReport as $type) {
                if ($e instanceof $type) {
                    return true;
                }
            }
            return false;
        }
    }
}

namespace Webman\Http {
    class Response
    {
        public int $status;
        public array $headers;
        public string $body;

        public function __construct($status = 200, array $headers = [], $body = '')
        {
            $this->status = $status;
            $this->headers = $headers;
            $this->body = $body;
        }
    }

    class Request
    {
        public function host() { return 'example.test:8787'; }
        public function method() { return 'POST'; }
        public function uri() { return '/login'; }
        public function getRealIp() { return '127.0.0.1'; }
        public function all() { return ['username' => 'demo', 'password' => 'secret123']; }
    }
}

namespace {
    use Melo\ExceptionHandler\Handler;
    use Melo\ExceptionHandler\Notification\HttpClientInterface;
    use Melo\ExceptionHandler\Notification\Notifier;

    $srcDir = dirname(__DIR__) . '/src/';
    $GLOBALS['cfg'] = [];

    function config(string $key, $default = null)
    {
        $value = $GLOBALS['cfg'];
        foreach (explode('.', $key) as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return $default;
            }
            $value = $value[$part];
        }
        return $value;
    }

    spl_autoload_register(static function (string $class) use ($srcDir): void {
        $prefix = 'Melo\\ExceptionHandler\\';
        if (str_starts_with($class, $prefix)) {
            $path = $srcDir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (is_file($path)) {
                require $path;
            }
        }
    });

    $failures = 0;
    function check(string $name, bool $ok, string $detail = ''): void
    {
        global $failures;
        if (!$ok) {
            $failures++;
        }
        echo ($ok ? 'PASS' : 'FAIL') . '  ' . $name . ($detail !== '' ? '  -> ' . $detail : '') . PHP_EOL;
    }

    class RecordingClient implements HttpClientInterface
    {
        public array $requests = [];
        public ?string $throwFor = null;

        public function post(string $url, array $payload, float $timeout = 2.0): void
        {
            if ($this->throwFor !== null && str_contains($url, $this->throwFor)) {
                throw new \RuntimeException('network down');
            }
            $this->requests[] = ['url' => $url, 'payload' => $payload, 'timeout' => $timeout];
        }
    }

    class TestHandler extends Handler
    {
        public ?Notifier $notifier = null;

        protected function createNotifier(array $config): Notifier
        {
            return $this->notifier ?? parent::createNotifier($config);
        }
    }

    function setNotifyConfig(array $notify): void
    {
        $GLOBALS['cfg'] = [
            'plugin' => [
                'melo' => [
                    'exception-handler' => [
                        'app' => ['exception_handler' => ['notify' => $notify]],
                    ],
                ],
            ],
        ];
    }

    $logger = new class {
        public array $logged = [];
        public function error(string $message): void
        {
            $this->logged[] = $message;
        }
    };

    $handler = new Handler($logger, false);
    $request = new \Webman\Http\Request();

    // ---- 原有核心行为回归 ----
    $r = $handler->render($request, new \Melo\ExceptionHandler\Exception\BadRequestHttpException('账号不能为空'));
    $body = json_decode($r->body, true);
    check('400 状态码', $r->status === 400, (string)$r->status);
    check('响应 JSON 字段', array_keys($body) === ['code', 'msg', 'data'], json_encode($body, JSON_UNESCAPED_UNICODE));
    check('错误消息', $body['msg'] === '账号不能为空', $body['msg']);
    check('生产模式 data 为空', $body['data'] === [], json_encode($body['data']));
    check('JSON UTF-8 头', ($r->headers['Content-Type'] ?? '') === 'application/json;charset=utf-8');

    $e = new \Melo\ExceptionHandler\Exception\BadRequestHttpException('验证码错误', [
        'errorCode' => 1001,
        'data' => ['field' => 'captcha'],
    ]);
    $body = json_decode($handler->render($request, $e)->body, true);
    check('业务错误码覆盖', $body['code'] === 1001, (string)$body['code']);
    check('扩展 data', $body['data'] === ['field' => 'captcha'], json_encode($body['data']));

    $body = json_decode($handler->render($request, new \Melo\ExceptionHandler\Exception\ServerErrorHttpException())->body, true);
    check('500 默认消息', $body['msg'] === 'Internal Server Error', $body['msg']);

    $body = json_decode($handler->render($request, new \RuntimeException('secret detail'))->body, true);
    check('隐藏服务器内部错误详情', $body['msg'] === 'Internal Server Error', $body['msg']);
    check('状态重置', $body['data'] === [], json_encode($body['data']));

    $GLOBALS['cfg'] = ['app' => ['debug' => true]];
    $body = json_decode($handler->render($request, new \RuntimeException('boom'))->body, true);
    check('调试模式请求信息', ($body['data']['request_url'] ?? '') === 'POST /login', json_encode($body['data']));
    check('调试模式堆栈', !empty($body['data']['error_trace']) && ($body['data']['error'] ?? '') === 'boom', json_encode($body['data']['error_trace'] ?? []));
    $GLOBALS['cfg'] = [];

    $config = require $srcDir . 'config/plugin/melo/exception-handler/app.php';
    $config['exception_handler']['body'] = ['error_code' => 200, 'message' => '自定义错误'];
    $GLOBALS['cfg'] = ['plugin' => ['melo' => ['exception-handler' => ['app' => $config]]]];
    $body = json_decode($handler->render($request, new \RuntimeException('x'))->body, true);
    check('自定义响应字段名', array_keys($body) === ['error_code', 'message', 'data'], json_encode($body, JSON_UNESCAPED_UNICODE));
    check('自定义默认 code', $body['error_code'] === 200, (string)$body['error_code']);

    $handler->report(new \Melo\ExceptionHandler\Exception\BadRequestHttpException('test'));
    check('dont_report 生效', count($handler->dontReport) > 0 && $logger->logged === [], json_encode($handler->dontReport));
    $GLOBALS['cfg'] = [];

    // ---- 通知功能回归 ----
    $notify = [
        'enable' => true,
        'async' => false,
        'min_status' => 500,
        'channels' => [
            'dingtalk' => [
                'enable' => true,
                'webhook' => 'https://oapi.dingtalk.com/robot/send?access_token=token',
                'secret' => 'ding-secret',
            ],
        ],
    ];
    setNotifyConfig($notify);
    $client = new RecordingClient();
    $notifyHandler = new TestHandler($logger, false);
    $notifyHandler->notifier = new Notifier($notify, $client);

    $response = $notifyHandler->render($request, new \RuntimeException('db down'));
    check('通知不影响响应', $response->status === 500 && json_decode($response->body, true)['msg'] === 'Internal Server Error');
    check('钉钉已发送', count($client->requests) === 1, json_encode($client->requests, JSON_UNESCAPED_UNICODE));

    $sent = $client->requests[0] ?? [];
    $query = [];
    parse_str((string)parse_url((string)($sent['url'] ?? ''), PHP_URL_QUERY), $query);
    $expectedSign = base64_encode(hash_hmac('sha256', ($query['timestamp'] ?? '') . "\n" . 'ding-secret', 'ding-secret', true));
    check('钉钉加签', ($query['sign'] ?? '') === $expectedSign, json_encode($query));
    check('钉钉 markdown 消息', ($sent['payload']['msgtype'] ?? '') === 'markdown', json_encode($sent['payload'], JSON_UNESCAPED_UNICODE));
    $text = (string)($sent['payload']['markdown']['text'] ?? '');
    check('通知含异常类与原始错误', str_contains($text, 'RuntimeException') && str_contains($text, 'db down'), $text);
    check('通知含请求上下文', str_contains($text, 'POST /login') && str_contains($text, '127.0.0.1'), $text);
    $GLOBALS['cfg'] = [];

    // 4xx 不通知
    setNotifyConfig($notify);
    $client = new RecordingClient();
    $notifyHandler->notifier = new Notifier($notify, $client);
    $notifyHandler->render($request, new \Melo\ExceptionHandler\Exception\BadRequestHttpException('bad'));
    check('业务 4xx 不通知', $client->requests === [], json_encode($client->requests));
    $GLOBALS['cfg'] = [];

    // dont_notify 排除
    $excluded = $notify;
    $excluded['dont_notify'] = [\RuntimeException::class];
    setNotifyConfig($excluded);
    $client = new RecordingClient();
    $notifyHandler->notifier = new Notifier($excluded, $client);
    $notifyHandler->render($request, new \RuntimeException('ignored'));
    check('dont_notify 排除生效', $client->requests === [], json_encode($client->requests));
    $GLOBALS['cfg'] = [];

    // 飞书签名 + 请求参数脱敏
    $feishu = [
        'enable' => true,
        'async' => false,
        'include_request_param' => true,
        'channels' => [
            'feishu' => [
                'enable' => true,
                'webhook' => 'https://open.feishu.cn/open-apis/bot/v2/hook/abc',
                'secret' => 'feishu-secret',
            ],
        ],
    ];
    setNotifyConfig($feishu);
    $client = new RecordingClient();
    $notifyHandler->notifier = new Notifier($feishu, $client);
    $notifyHandler->render($request, new \RuntimeException('boom'));
    $payload = $client->requests[0]['payload'] ?? [];
    $expectedFeishuSign = base64_encode(hash_hmac('sha256', ($payload['timestamp'] ?? '') . "\n" . 'feishu-secret', 'feishu-secret', true));
    check('飞书签名', ($payload['sign'] ?? '') === $expectedFeishuSign, json_encode($payload, JSON_UNESCAPED_UNICODE));
    check('飞书文本消息', ($payload['msg_type'] ?? '') === 'text', json_encode($payload, JSON_UNESCAPED_UNICODE));
    check('请求参数脱敏', str_contains((string)($payload['content']['text'] ?? ''), '"password":"******"'), (string)($payload['content']['text'] ?? ''));
    $GLOBALS['cfg'] = [];

    // 企业微信 + 渠道失败隔离
    $wecom = [
        'enable' => true,
        'async' => false,
        'channels' => [
            'dingtalk' => ['enable' => true, 'webhook' => 'https://bad.example.com/hook'],
            'wecom' => ['enable' => true, 'webhook' => 'https://qyapi.weixin.qq.com/cgi-bin/webhook/send?key=abc'],
        ],
    ];
    setNotifyConfig($wecom);
    $client = new RecordingClient();
    $client->throwFor = 'bad.example.com';
    $notifyHandler->notifier = new Notifier($wecom, $client);
    $response = $notifyHandler->render($request, new \RuntimeException('boom'));
    check('渠道失败不影响响应', $response->status === 500, json_encode(json_decode($response->body, true)));
    check('渠道失败隔离', count($client->requests) === 1 && str_contains($client->requests[0]['url'], 'qyapi.weixin.qq.com'), json_encode($client->requests));
    check('企业微信 markdown', ($client->requests[0]['payload']['msgtype'] ?? '') === 'markdown', json_encode($client->requests[0]['payload'] ?? []));
    $GLOBALS['cfg'] = [];

    // 异步发送
    \Workerman\Timer::$callbacks = [];
    $async = $notify;
    $async['async'] = true;
    $client = new RecordingClient();
    $notifyHandler->notifier = new Notifier($async, $client);
    $notifyHandler->render($request, new \RuntimeException('async'));
    check('异步模式不立即发送', $client->requests === [], json_encode($client->requests));
    \Workerman\Timer::runAll();
    check('异步回调执行发送', count($client->requests) === 1, json_encode($client->requests));
    $GLOBALS['cfg'] = [];

    echo $failures === 0 ? "\nALL PASS\n" : "\n{$failures} FAILED\n";
    exit($failures === 0 ? 0 : 1);
}
