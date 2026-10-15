<?php

use App\Exceptions\ApiException;
use App\Support\ApiErrorResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->shouldRenderJsonWhen(function (Request $request, \Throwable $e) {
            return $request->is('api/*') || $request->expectsJson();
        });

        $exceptions->render(function (ApiException $e, Request $request) {
            if (! ($request->is('api/*') || $request->expectsJson())) {
                return null;
            }

            return $e->render();
        });

        $exceptions->render(function (BadRequestHttpException $e, Request $request) {
            if (! ($request->is('api/*') || $request->expectsJson())) {
                return null;
            }

            return ApiErrorResponse::make(
                400,
                'MALFORMED_REQUEST',
                'The request could not be understood.',
            );
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if (! ($request->is('api/*') || $request->expectsJson())) {
                return null;
            }

            return ApiErrorResponse::make(
                401,
                'UNAUTHENTICATED',
                'Authentication is required.',
            );
        });

        $exceptions->render(function (AuthorizationException|AccessDeniedHttpException $e, Request $request) {
            if (! ($request->is('api/*') || $request->expectsJson())) {
                return null;
            }

            return ApiErrorResponse::make(
                403,
                'FORBIDDEN',
                'You are not allowed to perform this action.',
            );
        });

        $exceptions->render(function (ModelNotFoundException|NotFoundHttpException $e, Request $request) {
            if (! ($request->is('api/*') || $request->expectsJson())) {
                return null;
            }

            return ApiErrorResponse::make(
                404,
                'RESOURCE_NOT_FOUND',
                'The requested resource was not found.',
            );
        });

        $exceptions->render(function (ValidationException $e, Request $request) {
            if (! ($request->is('api/*') || $request->expectsJson())) {
                return null;
            }

            return ApiErrorResponse::make(
                422,
                'VALIDATION_ERROR',
                'The given data was invalid.',
                ['errors' => $e->errors()],
            );
        });

        $exceptions->render(function (\Throwable $e, Request $request) {
            if (! ($request->is('api/*') || $request->expectsJson())) {
                return null;
            }

            // Let more specific handlers above take precedence when applicable.
            if ($e instanceof ApiException
                || $e instanceof BadRequestHttpException
                || $e instanceof AuthenticationException
                || $e instanceof AuthorizationException
                || $e instanceof AccessDeniedHttpException
                || $e instanceof ModelNotFoundException
                || $e instanceof NotFoundHttpException
                || $e instanceof ValidationException) {
                return null;
            }

            report($e);

            return ApiErrorResponse::make(
                500,
                'INTERNAL_ERROR',
                'Unexpected server error.',
            );
        });
    })->create();
