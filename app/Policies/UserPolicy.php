<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Determine whether the user can view any user list.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('users.manage');
    }

    /**
     * Determine whether the user can view the specific user.
     */
    public function view(User $user, User $model): bool
    {
        return $user->hasPermission('users.manage') || $user->id === $model->id;
    }

    /**
     * Determine whether the user can create new users.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('users.manage');
    }

    /**
     * Determine whether the user can update the user.
     */
    public function update(User $user, User $model): bool
    {
        return $user->hasPermission('users.manage') || $user->id === $model->id;
    }

    /**
     * Determine whether the user can toggle the active status of the user.
     */
    public function toggleStatus(User $user, User $model): bool
    {
        // Admin cannot deactivate their own active account
        if ($user->id === $model->id) {
            return false;
        }

        return $user->hasPermission('users.manage');
    }

    /**
     * Determine whether the user can delete the user.
     */
    public function delete(User $user, User $model): bool
    {
        // Never allow deleting self
        if ($user->id === $model->id) {
            return false;
        }

        return $user->hasPermission('users.manage');
    }
}
