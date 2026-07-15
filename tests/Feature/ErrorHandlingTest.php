<?php

test('unknown api urls return the unified 404 envelope', function (): void {
    $this->getJson('/api/v1/nonexistent')
        ->assertNotFound()
        ->assertExactJson(['message' => 'Resource not found.']);
});

test('unknown api urls do not leak exception details', function (): void {
    $response = $this->getJson('/api/v1/nonexistent');

    $response->assertNotFound();

    expect($response->json())->toHaveKey('message')
        ->and($response->json())->not->toHaveKey('exception')
        ->and($response->json())->not->toHaveKey('trace');
});

test('wrong http verbs return the unified 405 envelope', function (): void {
    $this->patchJson(route('devices.index'))
        ->assertMethodNotAllowed()
        ->assertExactJson(['message' => 'Method not allowed.']);
});

test('web 404s keep the html error page', function (): void {
    $response = $this->get('/nonexistent');

    $response->assertNotFound();

    expect($response->headers->get('content-type'))->toContain('text/html');
});
