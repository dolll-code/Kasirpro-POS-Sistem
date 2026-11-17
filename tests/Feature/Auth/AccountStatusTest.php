<?php

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('active session is terminated on the next request when user account is deactivated', function () {
    $cashierRole = Role::where('code', 'cashier')->first();

    $user = User::create([
        'role_id' => $cashierRole->id,
        'name' => 'Session Test Cashier',
        'email' => 'session_test@kasirpro.local',
        'password' => Hash::make('password'),
        'is_active' => true,
    ]);

    // User is logged in and active
    $response1 = $this->actingAs($user)->get('/');
    $response1->assertStatus(200);
    $this->assertAuthenticatedAs($user);

    // Admin deactivates this user in the database
    $user->update(['is_active' => false]);

    // User attempts to make another request with the existing session
    $response2 = $this->actingAs($user)->get('/');

    // Session must be terminated and user redirected to login
    $this->assertGuest();
    $response2->assertRedirect(route('login'));
    $response2->assertSessionHas('error', 'Akun Anda telah dinonaktifkan. Silakan hubungi administrator.');

    // Audit log must record the termination
    $audit = AuditLog::where('user_id', $user->id)
        ->where('action', 'auth.session_terminated_inactive')
        ->latest('id')
        ->first();

    expect($audit)->not->toBeNull();
});

test('api request from deactivated user receives 401 unauthenticated with specific error code', function () {
    $cashierRole = Role::where('code', 'cashier')->first();

    $user = User::create([
        'role_id' => $cashierRole->id,
        'name' => 'API Inactive Cashier',
        'email' => 'api_inactive@kasirpro.local',
        'password' => Hash::make('password'),
        'is_active' => false,
    ]);

    $response = $this->actingAs($user)->getJson('/');

    $response->assertStatus(401);
    $response->assertJson([
        'error' => [
            'code' => 'UNAUTHENTICATED',
        ],
    ]);
});
