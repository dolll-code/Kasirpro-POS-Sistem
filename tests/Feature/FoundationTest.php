<?php

use App\Models\User;

test('guest is redirected from root to login', function () {
    $response = $this->get('/');

    $response->assertRedirect('/login');
});

test('foundation dashboard returns successful response for authenticated user and contains required components', function () {
    $admin = User::where('email', 'admin@kasirpro.local')->first();

    $response = $this->actingAs($admin)->get('/');

    $response->assertStatus(200);
    $response->assertSee('KasirPro Application Foundation');
    $response->assertSee('Tahap 01 Selesai');
    $response->assertSee('MySQL / InnoDB');
    $response->assertSee(config('app.timezone'));
});

test('incoming requests receive x-request-id header in response', function () {
    $response = $this->get('/login');

    $response->assertStatus(200);
    $response->assertHeader('X-Request-Id');
    expect($response->headers->get('X-Request-Id'))->not->toBeEmpty();
});

test('existing x-request-id is preserved by middleware', function () {
    $customId = 'test-correlation-uuid-12345';
    $response = $this->withHeader('X-Request-Id', $customId)->get('/login');

    $response->assertStatus(200);
    expect($response->headers->get('X-Request-Id'))->toBe($customId);
});
