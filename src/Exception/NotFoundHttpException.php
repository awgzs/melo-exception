<?php

declare(strict_types=1);

namespace Melo\ExceptionHandler\Exception;

/** 404：资源不存在。 */
class NotFoundHttpException extends BaseException
{
    public int $statusCode = 404;

    public string $errorMessage = '未找到请求的资源';
}
