<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Docente;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocenteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $search = $request->get('search', '');

        $query = Docente::query()->orderBy('nombre_completo');

        if ($search) {
            $query->where('nombre_completo', 'LIKE', "%{$search}%");
        }

        $docentes = $query->limit(50)->get(['id', 'nombre_completo']);

        return $this->success($docentes, 'Lista de docentes');
    }
}
