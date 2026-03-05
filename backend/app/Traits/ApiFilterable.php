<?php

namespace App\Traits;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

trait ApiFilterable
{
    /**
     * Aplica filtros de búsqueda, ordenamiento y paginación a una consulta.
     *
     * @param Builder $query La consulta Eloquent inicial
     * @param Request $request El objeto Request de Laravel
     * @param array $searchFields Campos en los que se debe buscar (ej: ['nombre', 'codigo'])
     * @param array $relationships Relaciones a buscar (ej: ['curso' => ['nombre', 'codigo']])
     * @param bool $applyDefaultOrder Si se debe aplicar ordenamiento por defecto (latest)
     * @return LengthAwarePaginator
     */
    protected function applyFiltersAndPaginate(Builder $query, Request $request, array $searchFields = [], array $relationships = [], bool $applyDefaultOrder = true): LengthAwarePaginator
    {
        // 1. Lógica de Búsqueda Global (Search)
        if ($request->filled('search')) {
            $search = $request->search;

            $query->where(function ($q) use ($search, $searchFields, $relationships) {
                // Buscar en campos directos del modelo
                foreach ($searchFields as $field) {
                    $q->orWhere($field, 'like', "%$search%");
                }

                // Buscar en campos de relaciones
                foreach ($relationships as $relation => $fields) {
                    $q->orWhereHas($relation, function ($sq) use ($search, $fields) {
                        $sq->where(function($innerQ) use ($search, $fields) {
                            foreach ($fields as $field) {
                                $innerQ->orWhere($field, 'like', "%$search%");
                            }
                        });
                    });
                }
            });
        }

        // 2. Filtros Específicos por campo
        // Procesar filtros para campos directos
        foreach ($searchFields as $field) {
            if ($request->filled($field)) {
                $query->where($field, 'like', '%' . $request->input($field) . '%');
            }
        }

        // Procesar filtros para relaciones
        foreach ($relationships as $relation => $fields) {
            foreach ($fields as $field) {
                $filterName = "{$relation}.{$field}";
                if ($request->filled($filterName)) {
                    $filterValue = $request->input($filterName);
                    $query->whereHas($relation, function ($q) use ($field, $filterValue) {
                        $q->where($field, 'like', "%{$filterValue}%");
                    });
                }
            }
        }

        // 3. Lógica de Paginación
        $perPage = $request->get('per_page', 10);

        // Aplicar ordenamiento por defecto solo si se solicita
        if ($applyDefaultOrder) {
            $query->latest();
        }

        return $query->paginate($perPage);
    }

    /**
     * Aplica filtros y devuelve respuesta JSON directamente.
     * Método de conveniencia para mantener compatibilidad.
     */
    protected function applyFiltersAndPaginateJson(Builder $query, Request $request, array $searchFields = [], array $relationships = []): JsonResponse
    {
        return response()->json($this->applyFiltersAndPaginate($query, $request, $searchFields, $relationships));
    }
}
