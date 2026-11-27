<?php

declare(strict_types=1);

namespace Melo\ExceptionHandler\Exception;

/** 500：服务器内部错误。 */
class ServerErrorHttpException extends BaseException
{
    public int $statusCode = 500;

    public string $errorMessage = 'Internal Server Error';
}
