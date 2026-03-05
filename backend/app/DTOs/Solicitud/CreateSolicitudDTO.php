<?php

namespace App\DTOs\Solicitud;

use Illuminate\Http\UploadedFile;

class CreateSolicitudDTO
{
    public function __construct(
        public readonly string $programacion_id,
        public readonly string $motivo,
        public readonly string $firma,
        public readonly ?UploadedFile $archivo_sustento,
        public readonly ?string $user_agent,
        public readonly ?string $ip
    ) {}

    public static function fromRequest(array $data, ?UploadedFile $file = null, ?string $userAgent = null, ?string $ip = null): self
    {
        return new self(
            programacion_id: $data['programacion_id'],
            motivo: $data['motivo'],
            firma: $data['firma'],
            archivo_sustento: $file,
            user_agent: $userAgent,
            ip: $ip
        );
    }
}
