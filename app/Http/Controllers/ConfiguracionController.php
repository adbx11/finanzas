<?php

namespace App\Http\Controllers;

use App\Models\Configuracion;
use App\Services\ConfiguracionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ConfiguracionController extends Controller
{
    public function __construct(
        private ConfiguracionService $configuracionService,
    ) {}

    public function index(): Response
    {
        $grouped = $this->configuracionService->grouped();

        $items = collect($grouped)->map(function ($rows, $grupo) {
            return [
                'grupo' => $grupo,
                'items' => collect($rows)->map(fn (Configuracion $c) => [
                    'id' => $c->id_configuracion,
                    'clave' => $c->clave,
                    'valor' => $c->valor,
                    'tipo' => $c->tipo,
                    'nombre' => $c->nombre,
                    'orden' => $c->orden,
                    'grupo' => $c->grupo,
                    'grupo_orden' => $c->grupo_orden,
                ])->values()->all(),
            ];
        })->values()->all();

        return Inertia::render('Configuracion/Index', [
            'groups' => $items,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'clave' => ['required', 'string', 'max:255', 'unique:configuracion,clave'],
            'valor' => ['nullable', 'string', 'max:255'],
            'tipo' => ['nullable', 'string', 'max:255'],
            'nombre' => ['nullable', 'string', 'max:255'],
            'grupo' => ['nullable', 'string', 'max:255'],
            'orden' => ['nullable', 'integer'],
            'grupo_orden' => ['nullable', 'integer'],
        ]);

        Configuracion::query()->create([
            'clave' => trim($data['clave']),
            'valor' => $data['valor'] ?? null,
            'tipo' => $data['tipo'] ?? 'string',
            'nombre' => $data['nombre'] ?? $data['clave'],
            'grupo' => $data['grupo'] ?? 'General',
            'orden' => $data['orden'] ?? 100,
            'grupo_orden' => $data['grupo_orden'] ?? 99,
        ]);

        return redirect()->route('configuracion.index')->with('success', 'Configuración creada.');
    }

    public function update(Request $request, Configuracion $configuracion): RedirectResponse
    {
        $data = $request->validate([
            'valor' => ['nullable', 'string', 'max:255'],
            'tipo' => ['nullable', 'string', 'max:255'],
            'nombre' => ['nullable', 'string', 'max:255'],
            'grupo' => ['nullable', 'string', 'max:255'],
            'orden' => ['nullable', 'integer'],
            'grupo_orden' => ['nullable', 'integer'],
        ]);

        $configuracion->update($data);

        return redirect()->route('configuracion.index')->with('success', 'Configuración actualizada.');
    }

    public function destroy(Configuracion $configuracion): RedirectResponse
    {
        $configuracion->delete();

        return redirect()->route('configuracion.index')->with('success', 'Configuración eliminada.');
    }
}
