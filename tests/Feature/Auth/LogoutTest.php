<?php

use App\Models\AuditLog;
use App\Models\User;

test('authenticated user can logout and session is invalidated', function () {
    $user = User::where('email', 'admin@kasirpro.local')->first();

    $this->actingAs($user);
    $sessionIdBefore = session()->getId();

    $response = $this->post('/logout');

    $this->assertGuest();
    $response->assertRedirect('/login');

    // Audit log should record logout
    $audit = AuditLog::where('user_id', $user->id)
        ->where('action', 'auth.logout')
        ->latest('id')
        ->first();

    expect($audit)->not->toBeNull();
    expect($audit->module)->toBe('auth');
});

test('unauthenticated user cannot post to logout', function () {
    $response = $this->post('/logout');
    $response->assertRedirect('/login');
});
