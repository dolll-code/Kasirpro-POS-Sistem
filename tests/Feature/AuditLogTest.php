<?php

use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditLogger;

test('audit logger records all mandatory audit metadata', function () {
    $admin = User::where('email', 'admin@kasirpro.local')->first();

    $this->actingAs($admin);

    $log = AuditLogger::log(
        action: 'test.sensitive_action',
        module: 'testing',
        reference: $admin,
        oldValues: ['status' => 'pending'],
        newValues: ['status' => 'approved'],
        userId: $admin->id
    );

    expect($log)->toBeInstanceOf(AuditLog::class);
    expect($log->user_id)->toBe($admin->id);
    expect($log->action)->toBe('test.sensitive_action');
    expect($log->module)->toBe('testing');
    expect($log->reference_type)->toBe(User::class);
    expect($log->reference_id)->toBe((string) $admin->id);
    expect($log->old_values)->toBe(['status' => 'pending']);
    expect($log->new_values)->toBe(['status' => 'approved']);
    expect($log->created_at)->not->toBeNull();
});

test('audit logger strictly redacts sensitive keys from old and new values', function () {
    $sensitivePayload = [
        'email' => 'user@test.com',
        'password' => 'superSecretPassword123',
        'password_confirmation' => 'superSecretPassword123',
        'remember_token' => 'tokenABCXYZ',
        'api_token' => 'secretBearerToken',
        'nested' => [
            'secret' => 'deepSecretKey',
            'public_key' => 'visibleKey123',
        ],
    ];

    $sanitized = AuditLogger::sanitize($sensitivePayload);

    expect($sanitized['email'])->toBe('user@test.com');
    expect($sanitized['password'])->toBe('[REDACTED]');
    expect($sanitized['password_confirmation'])->toBe('[REDACTED]');
    expect($sanitized['remember_token'])->toBe('[REDACTED]');
    expect($sanitized['nested']['secret'])->toBe('[REDACTED]');
    expect($sanitized['nested']['public_key'])->toBe('visibleKey123');
});
