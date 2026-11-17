<?php

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('passwords are securely hashed with bcrypt in the database', function () {
    $rawPassword = 'mySecretPassword123';
    $user = User::create([
        'role_id' => Role::where('code', 'cashier')->first()->id,
        'name' => 'Hash Test User',
        'email' => 'hash_test@kasirpro.local',
        'password' => Hash::make($rawPassword),
        'is_active' => true,
    ]);

    expect($user->password)->not->toBe($rawPassword);
    expect(Hash::check($rawPassword, $user->password))->toBeTrue();
});

test('users can update their own password when providing correct current password', function () {
    $user = User::where('email', 'admin@kasirpro.local')->first();

    $response = $this->actingAs($user)->put(route('profile.password.update'), [
        'current_password' => 'password',
        'password' => 'newSecretPassword88',
        'password_confirmation' => 'newSecretPassword88',
    ]);

    $response->assertSessionHas('success');
    $user->refresh();
    expect(Hash::check('newSecretPassword88', $user->password))->toBeTrue();

    // Verify audit log exists and does not contain raw passwords
    $audit = AuditLog::where('user_id', $user->id)
        ->where('action', 'auth.password_changed')
        ->latest('id')
        ->first();

    expect($audit)->not->toBeNull();
    $rawString = json_encode($audit->toArray());
    expect($rawString)->not->toContain('newSecretPassword88');
    expect($audit->new_values)->not->toHaveKey('password');
    expect($audit->new_values)->not->toHaveKey('current_password');

    // Restore password back to 'password' for test isolation
    $user->update(['password' => Hash::make('password')]);
});

test('password update fails when current password is wrong', function () {
    $user = User::where('email', 'admin@kasirpro.local')->first();

    $response = $this->actingAs($user)->put(route('profile.password.update'), [
        'current_password' => 'wrong_current_pwd',
        'password' => 'newSecretPassword88',
        'password_confirmation' => 'newSecretPassword88',
    ]);

    $response->assertSessionHasErrors('current_password');
    $user->refresh();
    expect(Hash::check('password', $user->password))->toBeTrue();
});
