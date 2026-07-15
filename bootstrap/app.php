<?php

declare(strict_types=1);

use App\Http\Middleware\LogApiRequest;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(LogApiRequest::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->render(function (NotFoundHttpException $notFoundHttpException, Request $request): ?JsonResponse {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json(['message' => 'Resource not found.'], JsonResponse::HTTP_NOT_FOUND);
        });

        $exceptions->render(function (MethodNotAllowedHttpException $methodNotAllowedHttpException, Request $request): ?JsonResponse {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json(['message' => 'Method not allowed.'], JsonResponse::HTTP_METHOD_NOT_ALLOWED);
        });
    })->create();
