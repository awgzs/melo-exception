<?php

declare(strict_types=1);

namespace Melo\ExceptionHandler;

use FastRoute\BadRouteException;
use Throwable;
use Webman\Exception\ExceptionHandler;
use Webman\Http\Request;
use Webman\Http\Response;

/**
 * 统一异常处理器：以 JSON 格式返回异常信息。
 */
class Handler extends ExceptionHandler
{
    /** @var string[] 不记录日志的异常类（父类属性无类型声明，子类须保持一致） */
    public $dontReport = [];

    /** HTTP 响应状态码 */
    public int $statusCode = 200;

    /** HTTP 响应头 */
    public array $header = [];

    /** 业务错误码 */
    public int $errorCode = 0;

    /** 业务错误消息 */
    public string $errorMessage = 'no error';

    /** 详细错误（用于调试信息） */
    public string $error = 'no error';

    /** @var array 响应 data 字段 */
    protected array $responseData = [];

    /** @var array 插件配置 */
    protected array $config = [];

    /** @var array 属性初始值，避免同一进程内多次请求之间串数据 */
    private array $initialState = [];

    public function __construct($logger = null, $debug = false)
    {
        parent::__construct($logger, $debug);
        $this->initialState = [
            'statusCode' => $this->statusCode,
            'header' => $this->header,
            'errorCode' => $this->errorCode,
            'errorMessage' => $this->errorMessage,
            'error' => $this->error,
        ];
    }

    public function report(Throwable $exception)
    {
        $this->dontReport = config('plugin.melo.exception-handler.app.exception_handler.dont_report', []) ?? [];
        parent::report($exception);
    }

    public function render(Request $request, Throwable $exception): Response
    {
        $this->resetState();
        $this->config = array_merge($this->defaultConfig(), config('plugin.melo.exception-handler.app.exception_handler', []) ?? []);

        $this->addRequestInfoToResponse($request);
        $this->solveAllException($exception);
        $this->addDebugInfoToResponse($exception);

        return $this->buildResponse();
    }

    /**
     * 每次渲染前重置状态，防止长驻进程中异常数据互相污染。
     */
    protected function resetState(): void
    {
        foreach ($this->initialState as $key => $value) {
            $this->{$key} = $value;
        }
        $this->responseData = [];
    }

    /**
     * 配置缺省值：即使未安装配置文件也能正常工作。
     */
    protected function defaultConfig(): array
    {
        return [
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
        ];
    }

    /**
     * 调试模式下附带请求上下文，生产模式只返回业务 data。
     */
    protected function addRequestInfoToResponse(Request $request): void
    {
        if (!$this->isDebug()) {
            return;
        }

        $this->responseData = array_merge($this->responseData, [
            'domain' => $request->host(),
            'method' => $request->method(),
            'request_url' => $request->method() . ' ' . $request->uri(),
            'timestamp' => date('Y-m-d H:i:s'),
            'client_ip' => $request->getRealIp(),
            'request_param' => $request->all(),
        ]);
    }

    /**
     * 解析异常，填充状态码、错误消息和响应数据。
     */
    protected function solveAllException(Throwable $e): void
    {
        if ($e instanceof Exception\BaseException) {
            $this->statusCode = $e->statusCode;
            $this->header = $e->header;
            $this->errorCode = $e->errorCode;
            $this->errorMessage = $e->errorMessage;
            $this->error = $e->error;
            if (!empty($e->data)) {
                $this->responseData = array_merge($this->responseData, $e->data);
            }
            if (!$e instanceof Exception\ServerErrorHttpException) {
                return;
            }
        }

        $this->solveExtraException($e);
    }

    /**
     * 处理框架异常及第三方插件异常；可被子类覆盖以扩展业务异常。
     */
    protected function solveExtraException(Throwable $e): void
    {
        $status = $this->config['status'];
        $this->errorMessage = $e->getMessage();

        if (class_exists(BadRouteException::class) && $e instanceof BadRouteException) {
            $this->statusCode = $status['route'] ?? 404;
        } elseif ($e instanceof \TypeError) {
            $this->statusCode = $status['type_error'] ?? 400;
            $this->errorMessage = !empty($status['type_error_is_response'])
                ? $e->getMessage()
                : '网络请求异常，请稍后重试';
            $this->error = $e->getMessage();
        } elseif ($e instanceof \InvalidArgumentException) {
            $this->statusCode = $status['invalid_argument'] ?? 415;
            $this->errorMessage = '预期参数配置异常：' . $e->getMessage();
            $this->error = $e->getMessage();
        } elseif (class_exists('Tinywan\Validate\Exception\ValidateException')
            && $e instanceof \Tinywan\Validate\Exception\ValidateException) {
            $this->statusCode = $status['validate'] ?? 400;
        } elseif (class_exists('Tinywan\Jwt\Exception\JwtTokenException')
            && $e instanceof \Tinywan\Jwt\Exception\JwtTokenException) {
            $this->statusCode = $status['jwt_token'] ?? 401;
        } elseif (class_exists('Tinywan\Jwt\Exception\JwtTokenExpiredException')
            && $e instanceof \Tinywan\Jwt\Exception\JwtTokenExpiredException) {
            $this->statusCode = $status['jwt_token_expired'] ?? 401;
        } elseif (class_exists('Tinywan\Jwt\Exception\JwtRefreshTokenExpiredException')
            && $e instanceof \Tinywan\Jwt\Exception\JwtRefreshTokenExpiredException) {
            $this->statusCode = $status['jwt_refresh_token_expired'] ?? 402;
        } elseif (class_exists('think\db\exception\DbException')
            && $e instanceof \think\db\exception\DbException) {
            $this->statusCode = 500;
            $this->errorMessage = 'Db：' . $e->getMessage();
            $this->error = $e->getMessage();
        } elseif ($e instanceof Exception\ServerErrorHttpException) {
            $this->statusCode = 500;
            $this->errorMessage = $e->errorMessage;
        } else {
            $this->statusCode = $status['server_error'] ?? 500;
            $this->errorMessage = !empty($status['server_error_is_response'])
                ? $e->getMessage()
                : 'Internal Server Error';
            $this->error = $e->getMessage();
        }
    }

    /**
     * app.debug = true 时返回详细堆栈，便于排查。
     */
    protected function addDebugInfoToResponse(Throwable $e): void
    {
        if (!$this->isDebug()) {
            return;
        }

        $this->responseData['error_message'] = $this->errorMessage;
        $this->responseData['error'] = $this->error;
        $this->responseData['error_trace'] = explode("\n", $e->getTraceAsString());
        $this->responseData['file'] = $e->getFile();
        $this->responseData['line'] = $e->getLine();
    }

    /**
     * 调试开关：优先读应用配置，未配置时使用 webman 传入的 debug 值。
     */
    protected function isDebug(): bool
    {
        return (bool)(config('app.debug', $this->debug) ?? $this->debug);
    }

    /**
     * 构造统一 JSON 响应；可被子类覆盖。
     */
    protected function buildResponse(): Response
    {
        $body = $this->config['body'] ?? [];
        $bodyKeys = array_keys($body);
        $bodyValues = array_values($body);

        $responseBody = [
            $bodyKeys[0] ?? 'code' => $this->errorCode > 0 ? $this->errorCode : ($bodyValues[0] ?? 0),
            $bodyKeys[1] ?? 'msg' => $this->errorMessage,
            $bodyKeys[2] ?? 'data' => $this->responseData,
        ];

        $header = array_merge(['Content-Type' => 'application/json;charset=utf-8'], $this->header);

        return new Response(
            $this->statusCode,
            $header,
            json_encode($responseBody, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE)
        );
    }
}
