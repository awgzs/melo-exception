# melo/exception-handler

Webman 异常处理插件：将异常统一转换为 JSON 响应，支持业务异常类、自定义状态码/响应体，以及调试模式下的详细堆栈。

## 环境要求

- PHP >= 8.0
- webman-framework ^1.5 或 ^2.0

## 安装

```bash
composer require melo/exception-handler
```

安装器会自动把配置复制到 `config/plugin/melo/exception-handler/app.php`。

编辑 `config/exception.php` 启用处理器：

```php
<?php

return [
    '' => Melo\ExceptionHandler\Handler::class,
];
```

多应用模式下可为每个应用单独配置，参见 [webman 多应用文档](https://www.workerman.net/doc/webman/multiapp.html)。

## 使用

```php
use Melo\ExceptionHandler\Exception\BadRequestHttpException;

public function login(\support\Request $request): \support\Response
{
    if (!$request->post('username')) {
        throw new BadRequestHttpException('账号不能为空');
    }
}
```

响应：

```http
HTTP/1.1 400 Bad Request
Content-Type: application/json;charset=utf-8

{
    "code": 0,
    "msg": "账号不能为空",
    "data": {}
}
```

附加业务数据：

```php
throw new BadRequestHttpException('验证码错误', [
    'errorCode' => 1001,
    'data' => ['field' => 'captcha'],
]);
```

```json
{
    "code": 1001,
    "msg": "验证码错误",
    "data": {"field": "captcha"}
}
```

## 内置异常类

| 异常类 | HTTP 状态码 |
| --- | --- |
| `BadRequestHttpException` | 400 |
| `UnauthorizedHttpException` | 401 |
| `ForbiddenHttpException` | 403 |
| `NotFoundHttpException` | 404 |
| `RouteNotFoundException` | 404 |
| `TooManyRequestsHttpException` | 429 |
| `ServerErrorHttpException` | 500 |

## 自定义异常类

继承 `BaseException` 并设置属性即可：

```php
<?php

namespace support\exception;

use Melo\ExceptionHandler\Exception\BaseException;

class MethodNotAllowedException extends BaseException
{
    public int $statusCode = 405;

    public string $errorMessage = '请求方法不被允许';
}
```

## 调试模式

`config/app.php` 中 `debug` 为 `true` 时，`data` 中额外返回请求上下文与堆栈：

```json
{
    "code": 0,
    "msg": "账号不能为空",
    "data": {
        "domain": "127.0.0.1:8787",
        "method": "POST",
        "request_url": "POST /login",
        "timestamp": "2026-11-27 10:00:00",
        "client_ip": "127.0.0.1",
        "request_param": {},
        "error_message": "账号不能为空",
        "error": "",
        "error_trace": ["..."],
        "file": "/app/app/controller/Auth.php",
        "line": 18
    }
}
```

生产模式只返回业务异常自带的 `data`，避免请求参数泄漏。

## 配置说明

配置文件：`config/plugin/melo/exception-handler/app.php`

```php
'exception_handler' => [
    'dont_report' => [/* 不写错误日志的异常类 */],
    'status' => [
        'route' => 404,
        'validate' => 400,
        'jwt_token' => 401,
        'jwt_token_expired' => 401,
        'jwt_refresh_token_expired' => 402,
        'server_error' => 500,
        'server_error_is_response' => false,
        'type_error' => 400,
        'type_error_is_response' => false,
        'invalid_argument' => 415,
    ],
    'body' => [
        'code' => 0,
        'msg' => '服务器内部异常',
        'data' => null,
    ],
],
```

- `body` 的前两个键可改名，例如改成 `error_code` / `message`，用于匹配前端约定；第三个键固定承载响应数据。
- `server_error_is_response` / `type_error_is_response` 控制是否把原始错误信息暴露给客户端，生产环境建议保持 `false`。

## 机器人通知

通知默认关闭，开启后仅对 `statusCode >= min_status`（默认 500）的异常发送消息，业务 4xx 不会刷屏。通知失败会被静默忽略，不影响返回给客户端的 JSON 响应。

```php
'notify' => [
    'enable' => true,
    'min_status' => 500,
    'async' => true,                // 基于 Workerman Timer 异步发送
    'timeout' => 2.0,
    'trace_lines' => 5,
    'include_raw_message' => true,  // 原始错误信息只进通知，不返回客户端
    'include_request_param' => false,
    'redact_keys' => ['password', 'token', 'secret', 'authorization'],
    'dont_notify' => [],            // 可按异常类排除
    'channels' => [
        'dingtalk' => [
            'enable' => true,
            'webhook' => getenv('DINGTALK_WEBHOOK'),
            'secret' => getenv('DINGTALK_SECRET'), // 加签，可选
        ],
        'feishu' => [
            'enable' => false,
            'webhook' => getenv('FEISHU_WEBHOOK'),
            'secret' => getenv('FEISHU_SECRET'),   // 签名校验，可选
        ],
        'wecom' => [
            'enable' => false,
            'webhook' => getenv('WECOM_WEBHOOK'),
        ],
    ],
],
```

- 钉钉使用 markdown 消息，配置 `secret` 时自动附加 `timestamp`/`sign`。
- 飞书使用文本消息，配置 `secret` 时自动附加签名。
- 企业微信使用 markdown 消息，无需签名。
- 请求参数默认不发送；开启 `include_request_param` 后，`redact_keys` 命中的字段会被掩码。
- webhook/secret 建议用环境变量注入，不要写进公开仓库。
- 从旧版升级时，重新安装或手动补全 `config/plugin/melo/exception-handler/app.php` 中的 `notify` 配置。

自定义渠道可实现 `Melo\ExceptionHandler\Notification\ChannelInterface`，覆盖 `Handler::createNotifier()` 注入自定义渠道列表。

## 扩展 Handler

继承 `Melo\ExceptionHandler\Handler` 覆盖对应方法：

```php
namespace support;

use Melo\ExceptionHandler\Handler;
use Webman\Http\Response;

class ErrorHandler extends Handler
{
    protected function solveExtraException(\Throwable $e): void
    {
        // 处理项目自己的异常类，然后按需调用父类逻辑
        parent::solveExtraException($e);
    }

    protected function buildResponse(): Response
    {
        // 自定义响应结构
        return parent::buildResponse();
    }
}
```

## 开发校验

无需安装额外依赖，可直接运行桩测试：

```bash
php tests/smoke.php
```

## License

MIT
