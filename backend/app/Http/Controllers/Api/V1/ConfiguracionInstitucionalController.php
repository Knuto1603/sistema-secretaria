<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ConfiguracionInstitucional;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConfiguracionInstitucionalController extends Controller
{
    public function index(): JsonResponse
    {
        $config = ConfiguracionInstitucional::orderBy('clave')->get()
            ->map(fn($c) => [
                'clave'       => $c->clave,
                'valor'       => $c->valor,
                'descripcion' => $c->descripcion,
            ]);

        return $this->success($config, 'Configuración institucional');
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'items'             => 'required|array',
            'items.*.clave'     => 'required|string|max:100',
            'items.*.valor'     => 'nullable|string',
        ]);

        foreach ($data['items'] as $item) {
            ConfiguracionInstitucional::where('clave', $item['clave'])
                ->update(['valor' => $item['valor']]);
        }

        $config = ConfiguracionInstitucional::orderBy('clave')->get()
            ->map(fn($c) => ['clave' => $c->clave, 'valor' => $c->valor, 'descripcion' => $c->descripcion]);

        return $this->success($config, 'Configuración actualizada');
    }
}
