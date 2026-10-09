<?php

use App\Exceptions\UserVisibleException;
use App\Services\ErrorNotificationService;
use App\Support\ApiErrorFormatter;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Явно регистрируем alias route middleware для Laravel 11 bootstrap-конфигурации.
        // Иначе legacy app/Http/Kernel.php может не примениться.
        $middleware->alias([
            'auth' => \App\Http\Middleware\Authenticate::class,
            'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,
            'role' => \App\Http\Middleware\RoleMiddleware::class,
            'device.api' => \App\Http\Middleware\CheckDeviceApiToken::class,
            'api.token' => \App\Http\Middleware\ApiTokenAuth::class,
            'request.id' => \App\Http\Middleware\RequestCorrelationId::class,
            'api.audit' => \App\Http\Middleware\ApiAuditLogMiddleware::class,
        ]);

        // Если нужно — сюда же можно добавить группы/глобальные middleware
        // $middleware->append(...);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->report(function (\Throwable $e): void {
            if ($e instanceof UserVisibleException) {
                return;
            }

            if ($e instanceof ValidationException || $e instanceof AuthenticationException) {
                return;
            }

            if ($e instanceof HttpExceptionInterface && $e->getStatusCode() < 500) {
                return;
            }

            $request = app()->bound('request') ? request() : null;
            $isApi = $request instanceof Request && ApiErrorFormatter::isApiRequest($request);

            $context = [
                'exception_class' => $e::class,
                'exception' => $e,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ];

            if ($request instanceof Request) {
                $context['request_id'] = ApiErrorFormatter::resolveRequestId($request);
                $context['request_method'] = $request->method();
                $context['request_path'] = $request->path();
                $context['request_url'] = $request->fullUrl();
                $context['request_ip'] = ApiErrorFormatter::resolveClientIp($request) ?? $request->ip();
                $context['request_user_agent'] = (string) $request->userAgent();
            }

            app(ErrorNotificationService::class)->log(
                'error',
                $isApi ? 'Unhandled API exception' : 'Unhandled exception',
                $context
            );
        });

        $exceptions->render(function (UserVisibleException $e, Request $request) {
            if (ApiErrorFormatter::isApiRequest($request)) {
                $extra = [];

                if ($e->field() !== null) {
                    $extra['errors'] = [
                        $e->field() => [$e->getMessage()],
                    ];
                }

                return ApiErrorFormatter::response(
                    $request,
                    $e->status(),
                    'user_visible_error',
                    $e->getMessage(),
                    $extra
                );
            }

            if ($e->field() !== null) {
                return back()
                    ->withErrors([$e->field() => $e->getMessage()])
                    ->withInput();
            }

            return back()
                ->with('err', $e->getMessage())
                ->withInput();
        });

        $exceptions->render(function (ValidationException $e, Request $request) {
            if (!ApiErrorFormatter::isApiRequest($request)) {
                return null;
            }

            return ApiErrorFormatter::response(
                $request,
                422,
                'validation_failed',
                'Ошибка валидации запроса',
                ['errors' => $e->errors()]
            );
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if (!ApiErrorFormatter::isApiRequest($request)) {
                return null;
            }

            return ApiErrorFormatter::response(
                $request,
                401,
                'unauthenticated',
                'Требуется авторизация'
            );
        });

        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if (!ApiErrorFormatter::isApiRequest($request)) {
                return null;
            }

            $status = $e->getStatusCode();
            $isServerError = $status >= 500;

            $message = $isServerError
                ? 'Внутренняя ошибка сервера'
                : ((string) $e->getMessage() !== ''
                    ? (string) $e->getMessage()
                    : (HttpResponse::$statusTexts[$status] ?? 'HTTP error'));

            return ApiErrorFormatter::response(
                $request,
                $status,
                $isServerError ? 'internal_server_error' : 'http_' . $status,
                $message
            );
        });

        $exceptions->render(function (\Throwable $e, Request $request) {
            if (!ApiErrorFormatter::isApiRequest($request)) {
                return null;
            }

            return ApiErrorFormatter::response(
                $request,
                500,
                'internal_server_error',
                'Внутренняя ошибка сервера'
            );
        });
    })
    ->create();
