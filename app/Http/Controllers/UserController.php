<?php

namespace App\Http\Controllers;

use App\Enums\UserPermission;
use App\Enums\UserRole;
use App\Http\Requests\Users\StoreUserRequest;
use App\Http\Requests\Users\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    /**
     * Display a paginated list of users.
     */
    public function index(): Response
    {
        Gate::authorize(UserPermission::UsersView->value);

        $users = User::query()
            ->with('roles')
            ->when(request('search'), function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->whereLike('name', "%{$search}%", false)
                        ->orWhereLike('email', "%{$search}%", false);
                });
            })
            ->when(request('role'), function ($query, string $role): void {
                $query->whereHas('roles', fn ($roleQuery) => $roleQuery->where('name', $role));
            })
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar_url' => $user->avatar_url,
                'whatsapp' => $user->whatsapp,
                'email_verified_at' => $user->email_verified_at?->toIso8601String(),
                'created_at' => $user->created_at->toIso8601String(),
                'roles' => $user->getRoleNames(),
            ]);

        return Inertia::render('users/index', [
            'users' => $users,
            'filters' => [
                'search' => request('search'),
                'role' => request('role'),
            ],
            'roles' => $this->visibleRoles(),
            'assignableRoles' => $this->assignableRoles(),
        ]);
    }

    /**
     * Store a newly created user.
     */
    public function store(StoreUserRequest $request): RedirectResponse
    {
        $validated = $request->safe()->except(['avatar']);

        $user = DB::transaction(function () use ($request, $validated): User {
            /** @var User $user */
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'whatsapp' => $validated['whatsapp'] ?? null,
            ]);

            if ($request->hasFile('avatar')) {
                $path = $request->file('avatar')->store('avatars', 'public');

                if ($path !== false) {
                    $user->avatar = $path;
                }

                $user->save();
            }

            $user->assignRole($validated['role']);

            return $user;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Pengguna :name berhasil ditambahkan.', ['name' => $user->name])]);

        return to_route('users.index');
    }

    /**
     * Update the given user.
     */
    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $this->ensureTargetIsManageable($user);

        $validated = $request->safe()->except(['avatar', 'remove_avatar', 'password']);
        $newRole = (string) $request->input('role');

        if (! $this->canChangeRoleOf($user, $newRole)) {
            throw ValidationException::withMessages([
                'role' => __('Minimal harus ada satu superadmin yang aktif.'),
            ]);
        }

        DB::transaction(function () use ($request, $user, $validated, $newRole): void {
            $user->fill($validated);

            if ($request->boolean('remove_avatar')) {
                $this->deleteAvatar($user);
            }

            if ($request->hasFile('avatar')) {
                $this->deleteAvatar($user);

                $path = $request->file('avatar')->store('avatars', 'public');

                if ($path !== false) {
                    $user->avatar = $path;
                }
            }

            if ($request->filled('password')) {
                $user->password = $request->input('password');
            }

            if ($user->isDirty('email')) {
                $user->email_verified_at = null;
            }

            $user->save();

            if ($user->hasRole($newRole) === false) {
                $user->syncRoles([$newRole]);
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profil pengguna berhasil diperbarui.')]);

        return back();
    }

    /**
     * Remove the given user.
     */
    public function destroy(User $user): RedirectResponse
    {
        Gate::authorize(UserPermission::UsersDelete->value);
        $this->ensureTargetIsManageable($user);

        if (auth()->id() === $user->id) {
            abort(403, __('Anda tidak dapat menghapus akun Anda sendiri.'));
        }

        if (! $this->canRemoveSuperadmin($user)) {
            abort(403, __('Minimal harus ada satu superadmin yang aktif.'));
        }

        $this->deleteAvatar($user);
        $user->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Pengguna berhasil dihapus.')]);

        return to_route('users.index');
    }

    /**
     * Role names that may appear in filters for the current actor.
     *
     * @return array<int, array{value: string, label: string}>
     */
    private function visibleRoles(): array
    {
        return Role::query()
            ->orderBy('name')
            ->get('name')
            ->map(fn (Role $role): array => ['value' => $role->name, 'label' => $role->name])
            ->all();
    }

    /**
     * Roles the current actor is allowed to assign.
     *
     * @return array<int, array{value: string, label: string}>
     */
    private function assignableRoles(): array
    {
        $actor = auth()->user();

        return Role::query()
            ->when(! $actor->hasRole(UserRole::SuperAdmin->value), fn ($query) => $query->where('name', '!=', UserRole::SuperAdmin->value))
            ->orderBy('name')
            ->get('name')
            ->map(fn (Role $role): array => ['value' => $role->name, 'label' => $role->name])
            ->all();
    }

    /**
     * Ensure the current actor may manage the target user.
     */
    private function ensureTargetIsManageable(User $target): void
    {
        $actor = auth()->user();

        if (! $actor->hasRole(UserRole::SuperAdmin->value) && $target->hasRole(UserRole::SuperAdmin->value)) {
            abort(403, __('Hanya superadmin yang dapat mengelola pengguna lain.'));
        }
    }

    /**
     * Determine whether the role of the given user may change to the new role.
     */
    private function canChangeRoleOf(User $user, string $newRole): bool
    {
        if ($user->hasRole(UserRole::SuperAdmin->value) === false || $newRole === UserRole::SuperAdmin->value) {
            return true;
        }

        return $this->otherActiveSuperadminsExist($user);
    }

    /**
     * Determine whether removing the given user keeps at least one superadmin.
     */
    private function canRemoveSuperadmin(User $user): bool
    {
        if ($user->hasRole(UserRole::SuperAdmin->value) === false) {
            return true;
        }

        return $this->otherActiveSuperadminsExist($user);
    }

    /**
     * Check whether another superadmin exists besides the given user.
     */
    private function otherActiveSuperadminsExist(User $except): bool
    {
        return User::whereKeyNot($except->getKey())
            ->whereHas('roles', fn ($query) => $query->where('name', UserRole::SuperAdmin->value))
            ->exists();
    }

    /**
     * Delete the stored avatar file for the given user.
     */
    private function deleteAvatar(User $user): void
    {
        if ($user->avatar !== null) {
            Storage::disk('public')->delete($user->avatar);
            $user->avatar = null;
        }
    }
}
