<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

test('login page can be rendered for guests', function () {
    $response = $this->get('/login');

    $response->assertStatus(200);
    $response->assertSee('KasirPro');
    $response->assertSee('Masuk ke Akun Anda');
});

test('authenticated user visiting login is redirected to dashboard', function () {
    $user = User::where('email', 'admin@kasirpro.local')->first();

    $response = $this->actingAs($user)->get('/login');

    $response->assertRedirect(route('dashboard'));
});

test('users can authenticate with valid credentials', function () {
    $user = User::where('email', 'admin@kasirpro.local')->first();

    $response = $this->post('/login', [
        'email' => 'admin@kasirpro.local',
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($user);
    $response->assertRedirect(route('dashboard'));

    $user->refresh();
    expect($user->last_login_at)->not->toBeNull();
});

test('users cannot authenticate with invalid credentials and generic error is returned without revealing email existence', function () {
    // 1. Non-existent email
    $response1 = $this->post('/login', [
        'email' => 'nonexistent@kasirpro.local',
        'password' => 'wrongpassword',
    ]);

    $this->assertGuest();
    $response1->assertSessionHasErrors('email');
    $error1 = session('errors')->first('email');

    // 2. Existing email with wrong password
    $response2 = $this->post('/login', [
        'email' => 'admin@kasirpro.local',
        'password' => 'wrongpassword',
    ]);

    $this->assertGuest();
    $response2->assertSessionHasErrors('email');
    $error2 = session('errors')->first('email');

    // Messages must be identical to avoid email enumeration
    expect($error1)->toBe($error2);
    expect($error1)->toBe('Email atau kata sandi yang Anda masukkan tidak sesuai.');
});

test('inactive users cannot authenticate even with correct password', function () {
    $inactiveUser = User::create([
        'role_id' => Role::where('code', 'cashier')->first()->id,
        'name' => 'Inactive Cashier',
        'email' => 'inactive@kasirpro.local',
        'password' => Hash::make('password'),
        'is_active' => false,
    ]);

    $response = $this->post('/login', [
        'email' => 'inactive@kasirpro.local',
        'password' => 'password',
    ]);

    $this->assertGuest();
    $response->assertSessionHasErrors('email');
    expect(session('errors')->first('email'))->toContain('dinonaktifkan');
});

test('login regenerates the session id', function () {
    $this->get('/login');
    $initialSessionId = session()->getId();

    $this->post('/login', [
        'email' => 'admin@kasirpro.local',
        'password' => 'password',
    ]);

    $newSessionId = session()->getId();
    expect($newSessionId)->not->toBe($initialSessionId);
});

test('login attempts are rate limited after 5 failed attempts', function () {
    RateLimiter::clear('admin@kasirpro.local|127.0.0.1');

    for ($i = 0; $i < 5; $i++) {
        $this->post('/login', [
            'email' => 'admin@kasirpro.local',
            'password' => 'wrongpassword',
        ]);
    }

    $response = $this->post('/login', [
        'email' => 'admin@kasirpro.local',
        'password' => 'password',
    ]);

    $response->assertSessionHasErrors('email');
    $errorMessage = session('errors')->first('email');
    expect($errorMessage)->toContain('detik');

    RateLimiter::clear('admin@kasirpro.local|127.0.0.1');
});
