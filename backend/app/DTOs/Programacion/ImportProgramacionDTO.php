<?php

namespace App\DTOs\Programacion;

use Illuminate\Http\UploadedFile;

class ImportProgramacionDTO
{
    public function __construct(
        public readonly UploadedFile $file,
        public readonly ?string $periodo_id
    ) {}

    public static function fromRequest(UploadedFile $file, ?string $periodoId = null): self
    {
        return new self(
            file: $file,
            periodo_id: $periodoId
        );
    }
}
