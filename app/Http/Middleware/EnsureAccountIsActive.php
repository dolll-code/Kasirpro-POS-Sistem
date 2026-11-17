<?php

namespace App\Http\Middleware;

use App\Services\AuditLogger;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountIsActive
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $user = Auth::user();

            if (! $user->is_active) {
                AuditLogger::log(
                    action: 'auth.session_terminated_inactive',
                    module: 'auth',
                    reference: $user,
                    oldValues: ['is_active' => false],
                    newValues: ['session' => 'invalidated'],
                    userId: $user->id
                );

                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                if ($request->expectsJson()) {
                    return response()->json([
                        'error' => [
                            'code' => 'UNAUTHENTICATED',
                            'message' => 'Akun Anda telah dinonaktifkan. Silakan hubungi administrator.',
                        ],
                    ], 401);
                }

                return redirect()->route('login')->with('error', 'Akun Anda telah dinonaktifkan. Silakan hubungi administrator.');
            }
        }

        return $next($request);
    }
}
