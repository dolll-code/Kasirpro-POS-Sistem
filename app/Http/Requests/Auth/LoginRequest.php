<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $credentials = $this->only('email', 'password');
        $user = User::where('email', $this->input('email'))->first();

        // 1. Check if user exists & password matches
        if (! Auth::validate($credentials)) {
            RateLimiter::hit($this->throttleKey());

            AuditLogger::log(
                action: 'auth.login_failed',
                module: 'auth',
                reference: $this->input('email'),
                oldValues: null,
                newValues: ['reason' => 'invalid_credentials']
            );

            // Generic error message without leaking whether email exists
            throw ValidationException::withMessages([
                'email' => __('Email atau kata sandi yang Anda masukkan tidak sesuai.'),
            ]);
        }

        // 2. Check if user account is active
        if ($user && ! $user->is_active) {
            RateLimiter::hit($this->throttleKey());

            AuditLogger::log(
                action: 'auth.login_rejected_inactive',
                module: 'auth',
                reference: $user,
                oldValues: ['is_active' => false],
                newValues: ['status' => 'rejected'],
                userId: $user->id
            );

            throw ValidationException::withMessages([
                'email' => __('Akun Anda dinonaktifkan. Silakan hubungi administrator toko.'),
            ]);
        }

        // 3. Successful login
        Auth::login($user, $this->boolean('remember'));

        RateLimiter::clear($this->throttleKey());

        // Update last login timestamp
        $user->forceFill(['last_login_at' => now()])->save();

        AuditLogger::log(
            action: 'auth.login_success',
            module: 'auth',
            reference: $user,
            oldValues: null,
            newValues: ['last_login_at' => now()->toIso8601String()],
            userId: $user->id
        );
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        AuditLogger::log(
            action: 'auth.login_rate_limited',
            module: 'auth',
            reference: $this->input('email'),
            oldValues: null,
            newValues: ['retry_after_seconds' => $seconds]
        );

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->input('email')).'|'.$this->ip());
    }
}
