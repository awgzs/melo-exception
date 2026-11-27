<?php

declare(strict_types=1);

namespace Melo\ExceptionHandler\Exception;

/** 401：未认证。 */
class UnauthorizedHttpException extends BaseException
{
    public int $statusCode = 401;

    public string $errorMessage = 'Unauthorized';
}
