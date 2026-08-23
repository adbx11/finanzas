<?php

namespace App\Http\Controllers;

use App\Http\Concerns\AppliesColumnFilters;
use App\Http\Concerns\AppliesTableSorting;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    use AppliesColumnFilters;
    use AppliesTableSorting;

    public function index(Request $request): Response
    {
        $query = User::query()->with('roles');

        $this->applyLikeFilter($query, $this->filterString($request, 'username'), 'username');
        $this->applyLikeFilter($query, $this->filterString($request, 'name'), 'name');
        $this->applyLikeFilter($query, $this->filterString($request, 'email'), 'email');

        if (($role = $this->filterString($request, 'role')) !== null) {
            $query->role($role);
        }

        if (($active = $this->filterString($request, 'active')) !== null) {
            $n = strtolower($active);
            if (in_array($n, ['s', 'si', 'sí', '1', 'true', 'yes'], true)) {
                $query->where('active', true);
            } elseif (in_array($n, ['n', 'no', '0', 'false'], true)) {
                $query->where('active', false);
            }
        }

        $this->applyTableSorting($query, $request, [
            'username' => fn ($q, string $d) => $q->orderBy('username', $d)->orderBy('id', $d),
            'name' => fn ($q, string $d) => $q->orderBy('name', $d)->orderBy('id', $d),
            'email' => fn ($q, string $d) => $q->orderBy('email', $d)->orderBy('id', $d),
            'active' => fn ($q, string $d) => $q->orderBy('active', $d)->orderBy('id', $d),
        ], fn ($q) => $q->orderBy('username'));

        $users = $query->get()->map(fn (User $u) => [
            'id' => $u->id,
            'username' => $u->username,
            'name' => $u->name,
            'email' => $u->email,
            'active' => (bool) $u->active,
            'role' => $u->roles->first()?->name,
            'roles' => $u->roles->pluck('name')->values()->all(),
        ]);

        return Inertia::render('Usuarios/Index', [
            'users' => $users,
            'roles' => User::availableRoleNames(),
            'filters' => $this->filterValues($request),
            'sort' => $this->sortColumn($request),
            'direction' => $this->sortDirection($request),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $role = $data['role'];
        unset($data['role']);

        $user = User::query()->create($data);
        $user->syncRoles([$role]);

        return redirect()->route('usuarios.index')->with('success', 'Usuario creado.');
    }

    public function update(Request $request, User $usuario): RedirectResponse
    {
        $data = $this->validated($request, $usuario);
        $role = $data['role'];
        unset($data['role']);

        if (empty($data['password'])) {
            unset($data['password']);
        }

        $this->guardLastAdmin($usuario, $role, (bool) ($data['active'] ?? true));

        $usuario->update($data);
        $usuario->syncRoles([$role]);

        return redirect()->route('usuarios.index')->with('success', 'Usuario actualizado.');
    }

    public function destroy(User $usuario): RedirectResponse
    {
        if ($usuario->id === Auth::id()) {
            return redirect()->route('usuarios.index')->with('error', 'No podés eliminar tu propio usuario.');
        }

        if ($usuario->hasRole('admin') && $this->adminCount() <= 1) {
            return redirect()->route('usuarios.index')->with('error', 'Debe quedar al menos un administrador activo.');
        }

        $usuario->delete();

        return redirect()->route('usuarios.index')->with('success', 'Usuario eliminado.');
    }

    private function validated(Request $request, ?User $user = null): array
    {
        if ($user && ! $request->filled('password')) {
            $request->merge([
                'password' => null,
                'password_confirmation' => null,
            ]);
        }

        $id = $user?->id;

        $rules = [
            'username' => ['required', 'string', 'max:64', Rule::unique('users', 'username')->ignore($id)],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($id)],
            'role' => ['required', 'string', Rule::in(User::availableRoleNames())],
            'active' => ['boolean'],
            'password' => [$user ? 'nullable' : 'required', 'confirmed', Password::defaults()],
        ];

        $data = $request->validate($rules);
        $data['active'] = (bool) ($data['active'] ?? true);
        $data['username'] = trim($data['username']);
        $data['name'] = trim($data['name']);
        $data['email'] = strtolower(trim($data['email']));

        Role::findOrCreate($data['role']);

        return $data;
    }

    private function guardLastAdmin(User $user, string $newRole, bool $active): void
    {
        if (! $user->hasRole('admin')) {
            return;
        }

        $losingAdmin = $newRole !== 'admin' || ! $active;
        if ($losingAdmin && $this->adminCount() <= 1) {
            throw ValidationException::withMessages([
                'role' => 'Debe quedar al menos un administrador activo.',
            ]);
        }
    }

    private function adminCount(): int
    {
        return User::query()->role('admin')->where('active', true)->count();
    }
}
