<?php

namespace App\DTOs\Developer;

class HealthDTO
{
    public function __construct(
        public readonly bool   $database,
        public readonly float  $disk_free_gb,
        public readonly float  $disk_total_gb,
        public readonly int    $disk_pct,
        public readonly string $php_version,
        public readonly string $laravel_version,
        public readonly string $environment,
        public readonly string $timestamp,
    ) {}
}
