<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\UpdatePasswordRequest;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the authenticated user's profile form.
     */
    public function show(): View
    {
        $user = Auth::user()->load('role.permissions');

        return view('profile.show', [
            'user' => $user,
        ]);
    }

    /**
     * Update the user's password.
     */
    public function updatePassword(UpdatePasswordRequest $request): RedirectResponse
    {
        $user = Auth::user();

        $user->forceFill([
            'password' => Hash::make($request->validated('password')),
        ])->save();

        AuditLogger::log(
            action: 'auth.password_changed',
            module: 'auth',
            reference: $user,
            oldValues: null,
            newValues: ['password_updated' => true],
            userId: $user->id
        );

        // Regenerate session ID for security after credential change
        $request->session()->regenerate();

        return back()->with('success', 'Kata sandi berhasil diperbarui.');
    }
}
