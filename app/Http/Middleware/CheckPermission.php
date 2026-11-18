<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        if (! Auth::check()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => [
                        'code' => 'UNAUTHENTICATED',
                        'message' => 'Anda belum terautentikasi.',
                    ],
                ], 401);
            }

            return redirect()->route('login');
        }

        $user = Auth::user();

        $permissions = explode('|', $permission);
        $hasAccess = false;
        foreach ($permissions as $p) {
            if ($user->hasPermission(trim($p))) {
                $hasAccess = true;
                break;
            }
        }

        if (! $hasAccess) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => [
                        'code' => 'FORBIDDEN',
                        'message' => 'Anda tidak memiliki hak akses untuk tindakan ini.',
                    ],
                ], 403);
            }

            abort(403, 'Anda tidak memiliki hak akses untuk mengakses halaman atau tindakan ini.');
        }

        return $next($request);
    }
}
