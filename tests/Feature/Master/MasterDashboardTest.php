<?php

use App\Models\Role;
use App\Models\User;

beforeEach(function () {
    $this->adminRole = Role::where('code', 'admin')->first();
    $this->admin = User::factory()->create([
        'role_id' => $this->adminRole->id,
        'is_active' => true,
    ]);
});

test('authenticated user can view master dashboard', function () {
    $response = $this->actingAs($this->admin)->get(route('master.index'));

    $response->assertOk();
    $response->assertViewIs('master.index');
    $response->assertSee('Pusat Data Master Toko');
    $response->assertSee('Katalog Produk');
    $response->assertSee('Kategori Produk');
    $response->assertSee('Satuan Presisi');
    $response->assertSee('Pemasok Terdaftar');
});

test('unauthenticated user is redirected to login from master dashboard', function () {
    $response = $this->get(route('master.index'));

    $response->assertRedirect(route('login'));
});
