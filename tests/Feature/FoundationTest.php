<?php

test('foundation home page returns successful response and contains required components', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
    $response->assertSee('KasirPro Application Foundation');
    $response->assertSee('Tahap 01 Selesai');
    $response->assertSee('MySQL / InnoDB');
    $response->assertSee(config('app.timezone'));
});

test('incoming requests receive x-request-id header in response', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
    $response->assertHeader('X-Request-Id');
    expect($response->headers->get('X-Request-Id'))->not->toBeEmpty();
});

test('existing x-request-id is preserved by middleware', function () {
    $customId = 'test-correlation-uuid-12345';
    $response = $this->withHeader('X-Request-Id', $customId)->get('/');

    $response->assertStatus(200);
    expect($response->headers->get('X-Request-Id'))->toBe($customId);
});
