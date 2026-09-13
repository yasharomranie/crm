<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * A single, reusable policy shared by every model that participates in the
 * dynamic access-control system (config/access-control.php). One instance
 * of this policy is bound per module (see AppServiceProvider), each closed
 * over its own module slug, so every action simply checks the matching
 * "{module}.{action}" permission - no per-model policy class is needed.
 */
class DynamicPermissionPolicy
{
    public function __construct(protected string $module) {}

    public function viewAny(User $user): bool
    {
        return $user->can("{$this->module}.view_any");
    }

    public function view(User $user, Model $record): bool
    {
        return $user->can("{$this->module}.view");
    }

    public function create(User $user): bool
    {
        return $user->can("{$this->module}.create");
    }

    public function update(User $user, Model $record): bool
    {
        return $user->can("{$this->module}.update");
    }

    public function delete(User $user, Model $record): bool
    {
        return $user->can("{$this->module}.delete");
    }

    public function deleteAny(User $user): bool
    {
        return $user->can("{$this->module}.delete_any");
    }

    public function restore(User $user, Model $record): bool
    {
        return $user->can("{$this->module}.update");
    }

    public function forceDelete(User $user, Model $record): bool
    {
        return $user->can("{$this->module}.delete");
    }
}
