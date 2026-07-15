<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DocsBasicAuth
{
    /**
     * Guard the documentation with HTTP Basic Auth, failing closed when
     * no credentials are configured.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $username = config()->string('docs.basic_auth.username', '');
        $password = config()->string('docs.basic_auth.password', '');

        if ($username === '' || $password === '') {
            return $this->unauthorized();
        }

        if (! hash_equals($username, (string) $request->getUser()) || ! hash_equals($password, (string) $request->getPassword())) {
            return $this->unauthorized();
        }

        return $next($request);
    }

    private function unauthorized(): Response
    {
        return response()->json(['message' => 'Unauthorized.'], Response::HTTP_UNAUTHORIZED, [
            'WWW-Authenticate' => 'Basic realm="API Documentation", charset="UTF-8"',
        ]);
    }
}
