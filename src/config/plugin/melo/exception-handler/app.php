<?php

declare(strict_types=1);

return [
    'enable' => true,
    'exception_handler' => [
        // 不记录错误日志的异常类
        'dont_report' => [
            Melo\ExceptionHandler\Exception\BadRequestHttpException::class,
            Melo\ExceptionHandler\Exception\UnauthorizedHttpException::class,
            Melo\ExceptionHandler\Exception\ForbiddenHttpException::class,
            Melo\ExceptionHandler\Exception\NotFoundHttpException::class,
            Melo\ExceptionHandler\Exception\RouteNotFoundException::class,
            Melo\ExceptionHandler\Exception\TooManyRequestsHttpException::class,
            Melo\ExceptionHandler\Exception\ServerErrorHttpException::class,
        ],
        // 各类异常的 HTTP 状态码
        'status' => [
            'route' => 404,                    // 路由不存在
            'validate' => 400,                 // tinywan/validate 验证器异常
            'jwt_token' => 401,                // 认证失败
            'jwt_token_expired' => 401,        // 访问令牌过期
            'jwt_refresh_token_expired' => 402, // 刷新令牌过期
            'server_error' => 500,             // 服务器内部错误
            'server_error_is_response' => false, // 是否向客户端返回服务器内部错误详情
            'type_error' => 400,               // 参数类型错误
            'type_error_is_response' => false, // 是否向客户端返回类型错误详情
            'invalid_argument' => 415,         // 非法参数
        ],
        // 响应体字段名及默认 code 值（第 3 个字段固定承载 data）
        'body' => [
            'code' => 0,
            'msg' => '服务器内部异常',
            'data' => null,
        ],
        // 机器人通知（默认关闭；建议 webhook/secret 通过环境变量注入，勿提交到仓库）
        'notify' => [
            'enable' => false,
            'min_status' => 500,          // 仅通知状态码 >= 该值的异常
            'async' => true,              // 基于 Workerman Timer 异步发送
            'timeout' => 2.0,             // 单个渠道超时（秒）
            'trace_lines' => 5,           // 堆栈摘要行数
            'include_raw_message' => true, // 通知中包含原始错误信息（不会返回给客户端）
            'include_request_param' => false,
            'redact_keys' => ['password', 'token', 'secret', 'authorization', 'access_token'],
            'dont_notify' => [],          // 不通知的异常类
            'channels' => [
                'dingtalk' => [
                    'enable' => false,
                    'webhook' => 'https://oapi.dingtalk.com/robot/send?access_token=xxx',
                    'secret' => '',       // 加签密钥，可选
                ],
                'feishu' => [
                    'enable' => false,
                    'webhook' => 'https://open.feishu.cn/open-apis/bot/v2/hook/xxx',
                    'secret' => '',       // 签名校验密钥，可选
                ],
                'wecom' => [
                    'enable' => false,
                    'webhook' => 'https://qyapi.weixin.qq.com/cgi-bin/webhook/send?key=xxx',
                ],
            ],
        ],
    ],
];
