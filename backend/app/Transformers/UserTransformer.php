<?php

namespace App\Transformers;

use App\DTOs\Auth\AuthenticatedUserDTO;
use App\Models\User;
use Illuminate\Support\Collection;

class UserTransformer
{
    public function toDTO(User $model): AuthenticatedUserDTO
    {
        return AuthenticatedUserDTO::fromUser($model);
    }

    public function toArray(User $model): array
    {
        return $this->toDTO($model)->toArray();
    }

    public function collection(Collection $models): array
    {
        return $models->map(fn($m) => $this->toArray($m))->toArray();
    }
}
