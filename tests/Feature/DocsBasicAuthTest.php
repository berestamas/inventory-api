<?php

test('docs are unreachable without credentials', function (): void {
    $this->get('/docs/api')
        ->assertUnauthorized()
        ->assertHeader('WWW-Authenticate');
});

test('docs are unreachable with wrong credentials', function (): void {
    $this->withHeaders(['Authorization' => 'Basic '.base64_encode('docs:wrong-password')])
        ->get('/docs/api')
        ->assertUnauthorized();
});

test('docs are unreachable with empty submitted credentials', function (): void {
    $this->withHeaders(['Authorization' => 'Basic '.base64_encode(':')])
        ->get('/docs/api')
        ->assertUnauthorized();
});

test('docs fail closed when no credentials are configured', function (): void {
    config(['docs.basic_auth.username' => '', 'docs.basic_auth.password' => '']);

    $this->withHeaders(['Authorization' => 'Basic '.base64_encode(':')])
        ->get('/docs/api')
        ->assertUnauthorized();
});

test('docs render with valid credentials', function (): void {
    $this->withHeaders(['Authorization' => 'Basic '.base64_encode('docs:secret')])
        ->get('/docs/api')
        ->assertSuccessful();
});

test('the openapi document is served with valid credentials and covers the api', function (): void {
    $response = $this->withHeaders(['Authorization' => 'Basic '.base64_encode('docs:secret')])
        ->get('/docs/api.json')
        ->assertSuccessful();

    expect($response->json('openapi'))->toStartWith('3.')
        ->and($response->json('paths'))->toHaveKeys([
            '/v1/devices',
            '/v1/devices/{device}',
            '/v1/contracts',
            '/v1/contracts/{contract}',
            '/v1/contracts/{contract}/devices',
            '/v1/contracts/{contract}/devices/{serialNumber}',
        ]);
});
