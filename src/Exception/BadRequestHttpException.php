<?php

declare(strict_types=1);

namespace Melo\ExceptionHandler\Exception;

/** 400：请求参数错误。 */
class BadRequestHttpException extends BaseException
{
    public int $statusCode = 400;

    public string $errorMessage = '请求参数错误，服务器不能或不会处理该请求';
}
