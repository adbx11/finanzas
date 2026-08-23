<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

class ImportLegacyUsersCommand extends Command
{
    protected $signature = 'finanzas:import-users
                            {--password= : Password bcrypt para todos los usuarios importados}
                            {--role=operador : Rol por defecto (admin|operador|lectura)}';

    protected $description = 'Importa usuarios desde sec_usuario a la tabla users de Laravel';

    public function handle(): int
    {
        if (! Schema::hasTable('sec_usuario')) {
            $this->error('No existe la tabla sec_usuario.');

            return self::FAILURE;
        }

        $password = $this->option('password') ?? 'dev123456';
        $defaultRole = $this->option('role');

        $legacyUsers = DB::table('sec_usuario')
            ->where(function ($q) {
                $q->whereNull('baja')->orWhere('baja', 0);
            })
            ->get();

        $imported = 0;

        foreach ($legacyUsers as $legacy) {
            if (empty($legacy->usuario)) {
                continue;
            }

            $email = $legacy->email ?: $legacy->usuario.'@finanzas.local';

            $user = User::query()->updateOrCreate(
                ['username' => $legacy->usuario],
                [
                    'name' => trim(($legacy->nombre ?? '').' '.($legacy->apellido ?? '')) ?: $legacy->usuario,
                    'email' => $email,
                    'password' => Hash::make($password),
                    'active' => true,
                ]
            );

            if ($legacy->perfil === 'admin' || str_contains(strtolower((string) $legacy->perfil), 'admin')) {
                $user->syncRoles(['admin']);
            } else {
                $user->syncRoles([$defaultRole]);
            }

            $imported++;
        }

        $this->info("Importados/actualizados: {$imported} usuarios.");
        $this->line("Password temporal: {$password}");

        if (! Role::query()->exists()) {
            $this->warn('Ejecutá php artisan db:seed --class=RolesSeeder primero.');
        }

        return self::SUCCESS;
    }
}
