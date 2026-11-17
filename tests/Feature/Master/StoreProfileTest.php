<?php

use App\Models\Role;
use App\Models\StoreProfile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');

    $this->adminRole = Role::where('code', 'admin')->first();
    $this->cashierRole = Role::where('code', 'cashier')->first();

    $this->admin = User::factory()->create([
        'role_id' => $this->adminRole->id,
        'is_active' => true,
    ]);

    $this->cashier = User::factory()->create([
        'role_id' => $this->cashierRole->id,
        'is_active' => true,
    ]);
});

test('admin can access store profile configuration while cashier is forbidden', function () {
    $this->actingAs($this->admin)->get(route('store-profile.edit'))->assertOk();
    $this->actingAs($this->cashier)->get(route('store-profile.edit'))->assertForbidden();
});

test('admin can update store profile and receipt width 58 and 80 mm', function () {
    $response = $this->actingAs($this->admin)->put(route('store-profile.update'), [
        'name' => 'Toko KasirPro Sejahtera',
        'address' => 'Jl. Merdeka No. 45, Jakarta',
        'phone' => '021-99887766',
        'email' => 'admin@tokokasirpro.local',
        'tax_id' => '02.999.888.7-011.000',
        'receipt_header' => 'Selamat Berbelanja di KasirPro',
        'receipt_footer' => 'Terima kasih atas kunjungan Anda!',
        'receipt_paper_width' => '80', // 80 mm
    ]);

    $response->assertRedirect(route('store-profile.edit'));
    $profile = StoreProfile::current();

    expect($profile->name)->toBe('Toko KasirPro Sejahtera')
        ->and($profile->receipt_paper_width)->toBe('80')
        ->and($profile->phone)->toBe('021-99887766');
});

test('invalid receipt paper width is rejected', function () {
    $response = $this->actingAs($this->admin)->put(route('store-profile.update'), [
        'name' => 'Toko KasirPro',
        'receipt_paper_width' => '100', // Invalid, only 58 or 80 allowed
    ]);

    $response->assertSessionHasErrors(['receipt_paper_width']);
});

test('admin can upload store logo with image validation', function () {
    $file = UploadedFile::fake()->image('store_logo.png', 400, 200);

    $response = $this->actingAs($this->admin)->put(route('store-profile.update'), [
        'name' => 'Toko KasirPro Berlogo',
        'receipt_paper_width' => '58',
        'logo' => $file,
    ]);

    $response->assertRedirect(route('store-profile.edit'));
    $profile = StoreProfile::current();

    expect($profile->logo_path)->not->toBeNull();
    Storage::disk('public')->assertExists($profile->logo_path);
});

test('non-image logo upload is rejected', function () {
    $file = UploadedFile::fake()->create('document.pdf', 500);

    $response = $this->actingAs($this->admin)->put(route('store-profile.update'), [
        'name' => 'Toko KasirPro',
        'receipt_paper_width' => '58',
        'logo' => $file,
    ]);

    $response->assertSessionHasErrors(['logo']);
});
