<?php

namespace App\Http\Controllers;

use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    /** @var array<string, string> */
    private const DESCRIPTIONS = [
        'admin' => 'Acceso completo, incluida la gestión de usuarios y configuración.',
        'operador' => 'Alta y edición de movimientos operativos (ingresos, pagos, asientos, etc.).',
        'lectura' => 'Solo consulta de listados e informes (sin modificar datos).',
    ];

    public function index(): Response
    {
        foreach (User::availableRoleNames() as $name) {
            Role::findOrCreate($name);
        }

        $roles = Role::query()
            ->whereIn('name', User::availableRoleNames())
            ->orderByRaw("FIELD(name, 'admin', 'operador', 'lectura')")
            ->get()
            ->map(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
                'description' => self::DESCRIPTIONS[$role->name] ?? '',
                'users_count' => User::query()->role($role->name)->count(),
                'active_users_count' => User::query()->role($role->name)->where('active', true)->count(),
            ]);

        return Inertia::render('Roles/Index', [
            'roles' => $roles,
        ]);
    }
}
