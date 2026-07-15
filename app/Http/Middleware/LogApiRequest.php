<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\ApiRequestLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogApiRequest
{
    private const int MAX_BODY_BYTES = 64000;

    private const array REDACTED_HEADERS = ['authorization', 'cookie', 'php-auth-pw'];

    private const array REDACTED_BODY_KEYS = ['password', 'password_confirmation', 'current_password', 'secret', 'token', 'api_key', 'access_token', 'refresh_token'];

    private const string STARTED_AT_ATTRIBUTE = 'log_api_request_started_at';

    /**
     * Stamp the start time on the request; the kernel resolves a fresh
     * middleware instance for terminate(), so state must live on the request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('api/*')) {
            $request->attributes->set(self::STARTED_AT_ATTRIBUTE, microtime(true));
        }

        return $next($request);
    }

    /**
     * Persist the request/response pair after the response has been sent.
     * A logging failure must never surface to the client.
     */
    public function terminate(Request $request, Response $response): void
    {
        if (! $request->is('api/*')) {
            return;
        }

        rescue(fn (): ApiRequestLog => ApiRequestLog::create([
            'method' => $request->method(),
            'path' => $request->path(),
            'query' => $request->query() === [] ? null : $request->query(),
            'request_headers' => $this->redactedHeaders($request),
            'request_body' => $this->truncated($request->getContent()),
            'status' => $response->getStatusCode(),
            'response_body' => $this->truncated($response->getContent()),
            'duration_ms' => $this->durationInMilliseconds($request),
            'ip' => (string) $request->ip(),
        ]), report: false);
    }

    /**
     * @return array<string, array<int, string|null>>
     */
    private function redactedHeaders(Request $request): array
    {
        return collect($request->headers->all())
            ->map(fn (array $values, string $name): array => in_array(strtolower($name), self::REDACTED_HEADERS, true)
                ? ['[redacted]']
                : $values)
            ->all();
    }

    /**
     * Redact sensitive JSON fields, then truncate to a storable size;
     * streamed responses yield no content.
     */
    private function truncated(string|false $content): ?string
    {
        if ($content === false || $content === '') {
            return null;
        }

        $decoded = json_decode($content, associative: true);

        if (is_array($decoded)) {
            $content = (string) json_encode($this->redactedValues($decoded));
        }

        return mb_strcut($content, 0, self::MAX_BODY_BYTES);
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @return array<array-key, mixed>
     */
    private function redactedValues(array $values): array
    {
        return collect($values)
            ->map(fn (mixed $value, int|string $key): mixed => match (true) {
                is_string($key) && in_array(strtolower($key), self::REDACTED_BODY_KEYS, true) => '[redacted]',
                is_array($value) => $this->redactedValues($value),
                default => $value,
            })
            ->all();
    }

    /**
     * Compute the elapsed time; null when handle() never ran for this request.
     */
    private function durationInMilliseconds(Request $request): ?int
    {
        $startedAt = $request->attributes->get(self::STARTED_AT_ATTRIBUTE);

        if (! is_float($startedAt)) {
            return null;
        }

        return (int) round((microtime(true) - $startedAt) * 1000);
    }
}
