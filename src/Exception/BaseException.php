<?php

declare(strict_types=1);

namespace Melo\ExceptionHandler\Exception;

/**
 * 所有业务异常的基类。
 */
class BaseException extends \Exception
{
    /** HTTP 响应状态码 */
    public int $statusCode = 400;

    /** HTTP 响应头 */
    public array $header = [];

    /** 业务错误码 */
    public int $errorCode = 0;

    /** 业务错误消息 */
    public string $errorMessage = 'The requested resource is not available or not exists';

    /** 附加响应数据 */
    public array $data = [];

    /** 详细错误（仅调试可见/日志使用） */
    public string $error = '';

    public function __construct(string $errorMessage = '', array $params = [], string $error = '')
    {
        parent::__construct($errorMessage, $this->statusCode);

        if ($errorMessage !== '') {
            $this->errorMessage = $errorMessage;
        }
        if ($error !== '') {
            $this->error = $error;
        }
        foreach (['statusCode', 'header', 'errorCode', 'data'] as $key) {
            if (array_key_exists($key, $params)) {
                $this->{$key} = $params[$key];
            }
        }
    }
}
