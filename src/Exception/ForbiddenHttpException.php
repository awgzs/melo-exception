<?php

declare(strict_types=1);

namespace Melo\ExceptionHandler\Exception;

/** 403：无权限访问资源。 */
class ForbiddenHttpException extends BaseException
{
    public int $statusCode = 403;

    public array $header = [
        'Access-Control-Allow-Origin' => '*',
        'Access-Control-Allow-Credentials' => 'true',
        'Access-Control-Allow-Headers' => 'Authorization,Content-Type,If-Match,If-Modified-Since,If-None-Match,If-Unmodified-Since,X-Requested-With,Origin',
        'Access-Control-Allow-Methods' => 'GET,POST,PUT,DELETE,OPTIONS',
    ];

    public string $errorMessage = '对不起，您没有该接口访问权限，请联系管理员';
}
