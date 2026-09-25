<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\ConflictHttpException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return \App\Http\ApiResponse::error($e->getMessage() ?: 'Conflict', null, 409);
            }
            return null;
        });

        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return \App\Http\ApiResponse::error($e->getMessage() ?: 'Unprocessable entity', null, 422);
            }
            return null;
        });

        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return \App\Http\ApiResponse::error('Not found', null, 404);
            }
            return null;
        });

        $exceptions->render(function (\Illuminate\Database\Eloquent\ModelNotFoundException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return \App\Http\ApiResponse::error('Not found', null, 404);
            }
            return null;
        });

        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return \App\Http\ApiResponse::error('Unauthenticated', null, 401);
            }
            return null;
        });

        $exceptions->render(function (\Illuminate\Database\QueryException $e, Request $request) {
            if (($request->is('api/*') || $request->expectsJson())
                && str_contains($e->getMessage(), 'registrations_active_key_unique')) {
                return \App\Http\ApiResponse::error('You already have an active registration for this extracurricular.', null, 409);
            }
            return null;
        });
    })->create();
