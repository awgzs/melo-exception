<?php

declare(strict_types=1);

namespace Melo\ExceptionHandler\Exception;

/** 429：请求过于频繁。 */
class TooManyRequestsHttpException extends BaseException
{
    public int $statusCode = 429;

    public array $header = [
        'Access-Control-Allow-Origin' => '*',
        'Access-Control-Allow-Credentials' => 'true',
        'Access-Control-Allow-Headers' => 'Authorization,Content-Type,If-Match,If-Modified-Since,If-None-Match,If-Unmodified-Since,X-Requested-With,Origin',
        'Access-Control-Allow-Methods' => 'GET,POST,PUT,DELETE,OPTIONS',
        'X-Rate-Limit-Limit' => 0,
        'X-Rate-Limit-Remaining' => 0,
        'X-Rate-Limit-Reset' => 0,
    ];

    public string $errorMessage = 'Too Many Requests, Please try again later';
}
