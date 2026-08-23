<?php

namespace App\Services;

use App\Models\Configuracion;

class ConfiguracionService
{
    public function get(string $clave, ?string $default = null): ?string
    {
        $row = Configuracion::query()->where('clave', $clave)->first();

        if (! $row || $row->valor === null || $row->valor === '') {
            return $default;
        }

        return (string) $row->valor;
    }

    public function set(string $clave, ?string $valor): void
    {
        $row = Configuracion::query()->where('clave', $clave)->first();
        if ($row) {
            $row->update(['valor' => $valor]);

            return;
        }

        Configuracion::query()->create([
            'clave' => $clave,
            'valor' => $valor,
            'tipo' => 'string',
            'nombre' => $clave,
            'orden' => 100,
            'grupo_orden' => 99,
            'grupo' => 'General',
        ]);
    }

    /**
     * @return array<string, list<Configuracion>>
     */
    public function grouped(): array
    {
        return Configuracion::query()
            ->orderBy('grupo_orden')
            ->orderBy('grupo')
            ->orderBy('orden')
            ->orderBy('clave')
            ->get()
            ->groupBy(fn (Configuracion $c) => $c->grupo ?: 'General')
            ->all();
    }
}
