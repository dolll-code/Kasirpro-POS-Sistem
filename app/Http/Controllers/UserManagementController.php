<?php

namespace App\Http\Controllers;

use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class UserManagementController extends Controller
{
    /**
     * Display a listing of the users.
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', User::class);

        $query = User::with('role');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('role')) {
            $query->whereHas('role', function ($q) use ($request) {
                $q->where('code', $request->input('role'));
            });
        }

        if ($request->filled('status')) {
            $isActive = $request->input('status') === 'active';
            $query->where('is_active', $isActive);
        }

        $users = $query->orderBy('name')->paginate(15)->withQueryString();
        $roles = Role::orderBy('name')->get();

        return view('users.index', [
            'users' => $users,
            'roles' => $roles,
        ]);
    }

    /**
     * Show the form for creating a new user.
     */
    public function create(): View
    {
        $this->authorize('create', User::class);

        $roles = Role::orderBy('name')->get();

        return view('users.create', [
            'roles' => $roles,
        ]);
    }

    /**
     * Store a newly created user in storage.
     */
    public function store(StoreUserRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $user = User::create([
            'role_id' => $validated['role_id'],
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'is_active' => $request->boolean('is_active', true),
        ]);

        AuditLogger::log(
            action: 'user.created',
            module: 'user_management',
            reference: $user,
            oldValues: null,
            newValues: [
                'name' => $user->name,
                'email' => $user->email,
                'role_id' => $user->role_id,
                'is_active' => $user->is_active,
            ]
        );

        return redirect()->route('users.index')->with('success', "Pengguna [{$user->name}] berhasil ditambahkan.");
    }

    /**
     * Display the specified user.
     */
    public function show(User $user): View
    {
        $this->authorize('view', $user);

        $user->load('role.permissions');

        $recentAudits = AuditLog::where(function ($q) use ($user) {
            $q->where('user_id', $user->id)
                ->orWhere(function ($sub) use ($user) {
                    $sub->where('reference_type', User::class)
                        ->where('reference_id', (string) $user->id);
                });
        })->latest('id')->limit(10)->get();

        return view('users.show', [
            'user' => $user,
            'recentAudits' => $recentAudits,
        ]);
    }

    /**
     * Show the form for editing the specified user.
     */
    public function edit(User $user): View
    {
        $this->authorize('update', $user);

        $roles = Role::orderBy('name')->get();

        return view('users.edit', [
            'user' => $user,
            'roles' => $roles,
        ]);
    }

    /**
     * Update the specified user in storage.
     */
    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $validated = $request->validated();

        $oldValues = [
            'name' => $user->name,
            'email' => $user->email,
            'role_id' => $user->role_id,
            'is_active' => $user->is_active,
        ];

        $updateData = [
            'role_id' => $validated['role_id'],
            'name' => $validated['name'],
            'email' => $validated['email'],
            'is_active' => $request->boolean('is_active', $user->is_active),
        ];

        if (! empty($validated['password'])) {
            $updateData['password'] = Hash::make($validated['password']);
        }

        $user->update($updateData);

        AuditLogger::log(
            action: 'user.updated',
            module: 'user_management',
            reference: $user,
            oldValues: $oldValues,
            newValues: [
                'name' => $user->name,
                'email' => $user->email,
                'role_id' => $user->role_id,
                'is_active' => $user->is_active,
                'password_changed' => ! empty($validated['password']),
            ]
        );

        return redirect()->route('users.index')->with('success', "Data pengguna [{$user->name}] berhasil diperbarui.");
    }

    /**
     * Toggle active/inactive status of a user.
     */
    public function toggleStatus(User $user): RedirectResponse
    {
        $this->authorize('toggleStatus', $user);

        $oldStatus = $user->is_active;
        $newStatus = ! $oldStatus;

        $user->update(['is_active' => $newStatus]);

        AuditLogger::log(
            action: $newStatus ? 'user.activated' : 'user.deactivated',
            module: 'user_management',
            reference: $user,
            oldValues: ['is_active' => $oldStatus],
            newValues: ['is_active' => $newStatus]
        );

        $statusText = $newStatus ? 'diaktifkan' : 'dinonaktifkan';

        return back()->with('success', "Akun pengguna [{$user->name}] berhasil {$statusText}.");
    }
}
