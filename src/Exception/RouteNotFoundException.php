<?php

declare(strict_types=1);

namespace Melo\ExceptionHandler\Exception;

/** 404：路由不存在。 */
class RouteNotFoundException extends BaseException
{
    public int $statusCode = 404;

    public string $errorMessage = '路由地址不存在';
}
