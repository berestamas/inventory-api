<?php

use App\Enums\DeviceCategory;
use App\Models\ApiRequestLog;
use App\Models\Device;
use Illuminate\Support\Str;

test('api requests are logged with method, path, status, and duration', function (): void {
    Device::factory()->create();

    $this->getJson(route('devices.index'))->assertSuccessful();

    $apiRequestLog = ApiRequestLog::query()->sole();

    expect($apiRequestLog->method)->toBe('GET')
        ->and($apiRequestLog->path)->toBe('api/v1/devices')
        ->and($apiRequestLog->status)->toBe(200)
        ->and($apiRequestLog->duration_ms)->not->toBeNull()
        ->and($apiRequestLog->response_body)->toContain('"data"');
});

test('query parameters are logged', function (): void {
    $this->getJson(route('devices.index', ['filter' => ['name' => 'think']]))->assertSuccessful();

    $apiRequestLog = ApiRequestLog::query()->sole();

    expect($apiRequestLog->query)->toBe(['filter' => ['name' => 'think']]);
});

test('request bodies are logged and sensitive headers are redacted', function (): void {
    $this->postJson(route('devices.store'), [
        'name' => 'ThinkPad X1 Carbon',
        'manufacturer' => 'Lenovo',
        'category' => DeviceCategory::Laptop->value,
    ], ['Authorization' => 'Bearer super-secret-token'])->assertCreated();

    $apiRequestLog = ApiRequestLog::query()->sole();

    expect($apiRequestLog->request_body)->toContain('ThinkPad X1 Carbon')
        ->and($apiRequestLog->request_headers['authorization'])->toBe(['[redacted]'])
        ->and($apiRequestLog->status)->toBe(201);
});

test('oversized bodies are truncated before storage', function (): void {
    $this->postJson(route('devices.store'), [
        'name' => str_repeat('a', 100000),
        'manufacturer' => 'Lenovo',
        'category' => DeviceCategory::Laptop->value,
    ])->assertUnprocessable();

    $apiRequestLog = ApiRequestLog::query()->sole();

    expect(strlen((string) $apiRequestLog->request_body))->toBeLessThanOrEqual(64000)
        ->and($apiRequestLog->status)->toBe(422);
});

test('unmatched api urls are logged with their 404 status', function (): void {
    $this->getJson('/api/v1/nonexistent')->assertNotFound();

    $apiRequestLog = ApiRequestLog::query()->sole();

    expect($apiRequestLog->path)->toBe('api/v1/nonexistent')
        ->and($apiRequestLog->status)->toBe(404);
});

test('unknown resource 404s are logged', function (): void {
    $this->getJson(route('devices.show', ['device' => (string) Str::uuid7()]))->assertNotFound();

    expect(ApiRequestLog::query()->sole()->status)->toBe(404);
});

test('method-not-allowed requests are logged', function (): void {
    $this->patchJson(route('devices.index'))->assertMethodNotAllowed();

    expect(ApiRequestLog::query()->sole()->status)->toBe(405);
});

test('non-api requests are not logged', function (): void {
    $this->get('/')->assertSuccessful();
    $this->get('/up')->assertSuccessful();

    expect(ApiRequestLog::query()->count())->toBe(0);
});

test('logs older than thirty days are prunable', function (): void {
    $old = ApiRequestLog::factory()->create(['created_at' => now()->subDays(31)]);
    $fresh = ApiRequestLog::factory()->create(['created_at' => now()->subDay()]);

    $this->artisan('model:prune', ['--model' => [ApiRequestLog::class]])->assertSuccessful();

    $this->assertDatabaseMissing('api_request_logs', ['id' => $old->id]);
    $this->assertDatabaseHas('api_request_logs', ['id' => $fresh->id]);
});
