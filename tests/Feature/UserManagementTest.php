<?php

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('admin with users.manage permission can view user management list', function () {
    $admin = User::where('email', 'admin@kasirpro.local')->first();

    $response = $this->actingAs($admin)->get(route('users.index'));

    $response->assertStatus(200);
    $response->assertSee('Daftar Pengguna Sistem');
    $response->assertSee($admin->name);
});

test('supervisor without users.manage permission receives 403 when accessing user management', function () {
    $supervisor = User::where('email', 'supervisor@kasirpro.local')->first();

    $response = $this->actingAs($supervisor)->get(route('users.index'));

    $response->assertStatus(403);
});

test('cashier receives 403 when accessing user management', function () {
    $cashier = User::where('email', 'kasir@kasirpro.local')->first();

    $response = $this->actingAs($cashier)->get(route('users.index'));

    $response->assertStatus(403);
});

test('admin can create a new user and assign a role', function () {
    $admin = User::where('email', 'admin@kasirpro.local')->first();
    $cashierRole = Role::where('code', 'cashier')->first();

    $response = $this->actingAs($admin)->post(route('users.store'), [
        'name' => 'Budi Kasir Baru',
        'email' => 'budi@kasirpro.local',
        'password' => 'secretPassword123',
        'role_id' => $cashierRole->id,
        'is_active' => true,
    ]);

    $response->assertRedirect(route('users.index'));
    $response->assertSessionHas('success');

    $createdUser = User::where('email', 'budi@kasirpro.local')->first();
    expect($createdUser)->not->toBeNull();
    expect($createdUser->role_id)->toBe($cashierRole->id);
    expect($createdUser->is_active)->toBeTrue();

    // Verify audit log
    $audit = AuditLog::where('action', 'user.created')
        ->where('reference_id', (string) $createdUser->id)
        ->latest('id')
        ->first();

    expect($audit)->not->toBeNull();
    expect($audit->new_values['email'])->toBe('budi@kasirpro.local');
});

test('creating user validates unique email', function () {
    $admin = User::where('email', 'admin@kasirpro.local')->first();
    $cashierRole = Role::where('code', 'cashier')->first();

    $response = $this->actingAs($admin)->post(route('users.store'), [
        'name' => 'Duplikat Admin',
        'email' => 'admin@kasirpro.local', // Already taken
        'password' => 'secretPassword123',
        'role_id' => $cashierRole->id,
    ]);

    $response->assertSessionHasErrors('email');
});

test('admin can update user details and role change is audited', function () {
    $admin = User::where('email', 'admin@kasirpro.local')->first();
    $cashierRole = Role::where('code', 'cashier')->first();
    $supervisorRole = Role::where('code', 'supervisor')->first();

    $user = User::create([
        'role_id' => $cashierRole->id,
        'name' => 'Promotion Test User',
        'email' => 'promote@kasirpro.local',
        'password' => Hash::make('password'),
        'is_active' => true,
    ]);

    $response = $this->actingAs($admin)->put(route('users.update', $user), [
        'name' => 'Promoted Supervisor',
        'email' => 'promote@kasirpro.local',
        'role_id' => $supervisorRole->id, // Promoted
        'is_active' => true,
    ]);

    $response->assertRedirect(route('users.index'));
    $user->refresh();
    expect($user->name)->toBe('Promoted Supervisor');
    expect($user->role_id)->toBe($supervisorRole->id);

    // Verify audit log recorded old and new role_id
    $audit = AuditLog::where('action', 'user.updated')
        ->where('reference_id', (string) $user->id)
        ->latest('id')
        ->first();

    expect($audit)->not->toBeNull();
    expect($audit->old_values['role_id'])->toBe($cashierRole->id);
    expect($audit->new_values['role_id'])->toBe($supervisorRole->id);
});

test('admin can toggle user status between active and inactive', function () {
    $admin = User::where('email', 'admin@kasirpro.local')->first();
    $cashier = User::where('email', 'kasir@kasirpro.local')->first();

    $initialStatus = $cashier->is_active;

    $response = $this->actingAs($admin)->patch(route('users.toggle_status', $cashier));

    $response->assertSessionHas('success');
    $cashier->refresh();
    expect($cashier->is_active)->toBe(! $initialStatus);

    // Revert status
    $cashier->update(['is_active' => true]);
});

test('admin cannot deactivate their own active account', function () {
    $admin = User::where('email', 'admin@kasirpro.local')->first();

    $response = $this->actingAs($admin)->patch(route('users.toggle_status', $admin));

    $response->assertStatus(403);
    $admin->refresh();
    expect($admin->is_active)->toBeTrue();
});
