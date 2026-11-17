<?php

use App\Models\User;
use Illuminate\Support\Facades\Gate;

test('admin role has all required system permissions', function () {
    $admin = User::where('email', 'admin@kasirpro.local')->first();

    expect($admin->hasPermission('users.manage'))->toBeTrue();
    expect($admin->hasPermission('products.create'))->toBeTrue();
    expect($admin->hasPermission('purchases.confirm'))->toBeTrue();
    expect($admin->hasPermission('sales.create'))->toBeTrue();
    expect($admin->hasPermission('sales.view_all'))->toBeTrue();
    expect($admin->hasPermission('returns.approve'))->toBeTrue();
    expect($admin->hasPermission('reports.view_all'))->toBeTrue();
    expect($admin->hasPermission('audit.view'))->toBeTrue();
});

test('supervisor has operational permissions but does not have users.manage or catalog.manage by default', function () {
    $supervisor = User::where('email', 'supervisor@kasirpro.local')->first();

    expect($supervisor->hasPermission('dashboard.view'))->toBeTrue();
    expect($supervisor->hasPermission('products.view'))->toBeTrue();
    expect($supervisor->hasPermission('purchases.confirm'))->toBeTrue();
    expect($supervisor->hasPermission('payments.verify_transfer'))->toBeTrue();
    expect($supervisor->hasPermission('returns.approve'))->toBeTrue();
    expect($supervisor->hasPermission('sales.view_all'))->toBeTrue();

    // Denied permissions
    expect($supervisor->hasPermission('users.manage'))->toBeFalse();
    expect($supervisor->hasPermission('roles.manage'))->toBeFalse();
    expect($supervisor->hasPermission('catalog.manage'))->toBeFalse();
    expect($supervisor->hasPermission('store_settings.manage'))->toBeFalse();
});

test('cashier only has POS and own transactions permissions', function () {
    $cashier = User::where('email', 'kasir@kasirpro.local')->first();

    expect($cashier->hasPermission('dashboard.view'))->toBeTrue();
    expect($cashier->hasPermission('products.view'))->toBeTrue();
    expect($cashier->hasPermission('sales.create'))->toBeTrue();
    expect($cashier->hasPermission('sales.view_own'))->toBeTrue();
    expect($cashier->hasPermission('returns.request'))->toBeTrue();

    // Denied permissions
    expect($cashier->hasPermission('sales.view_all'))->toBeFalse();
    expect($cashier->hasPermission('payments.verify_transfer'))->toBeFalse();
    expect($cashier->hasPermission('returns.approve'))->toBeFalse();
    expect($cashier->hasPermission('purchases.create'))->toBeFalse();
    expect($cashier->hasPermission('users.manage'))->toBeFalse();
});

test('cashier cannot view other cashier transaction (IDOR resource ownership check)', function () {
    $cashier1 = User::where('email', 'kasir@kasirpro.local')->first();
    $cashier2Id = 999; // Different cashier

    // Cashier 1 attempts to view cashier 2's sale
    expect(Gate::forUser($cashier1)->allows('view-sale', $cashier2Id))->toBeFalse();

    // Cashier 1 can view their own sale
    expect(Gate::forUser($cashier1)->allows('view-sale', $cashier1->id))->toBeTrue();
});

test('supervisor and admin can view any cashier transaction via sales.view_all permission', function () {
    $admin = User::where('email', 'admin@kasirpro.local')->first();
    $supervisor = User::where('email', 'supervisor@kasirpro.local')->first();
    $randomCashierId = 123;

    expect(Gate::forUser($admin)->allows('view-sale', $randomCashierId))->toBeTrue();
    expect(Gate::forUser($supervisor)->allows('view-sale', $randomCashierId))->toBeTrue();
});

test('unauthorized route access renders custom 403 page with correlation request id', function () {
    $cashier = User::where('email', 'kasir@kasirpro.local')->first();

    $response = $this->actingAs($cashier)->withHeader('X-Request-Id', 'TEST-CORRELATION-403')->get('/users');

    $response->assertStatus(403);
    $response->assertSee('Akses Ditolak');
    $response->assertSee('TEST-CORRELATION-403');
});
