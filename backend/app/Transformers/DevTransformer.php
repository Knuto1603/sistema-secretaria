<?php

namespace App\Transformers;

use App\DTOs\Developer\ActivityLogDTO;
use App\DTOs\Developer\HealthDTO;
use App\DTOs\Developer\SystemSettingDTO;
use App\Models\ActivityLog;
use App\Models\SystemSetting;

class DevTransformer
{
    public static function toActivityLogDTO(ActivityLog $log): ActivityLogDTO
    {
        return new ActivityLogDTO(
            id:                  $log->id,
            user:                $log->user ? ['id' => $log->user->id, 'name' => $log->user->name] : null,
            accion:              $log->accion,
            modelo:              $log->modelo,
            modelo_id:           $log->modelo_id,
            valores_anteriores:  $log->valores_anteriores,
            valores_nuevos:      $log->valores_nuevos,
            ip:                  $log->ip,
            created_at:          $log->created_at->toISOString(),
        );
    }

    public static function toSystemSettingDTO(SystemSetting $setting): SystemSettingDTO
    {
        return new SystemSettingDTO(
            key:         $setting->key,
            value:       $setting->value,
            type:        $setting->type,
            grupo:       $setting->grupo,
            descripcion: $setting->descripcion,
        );
    }

    public static function toHealthDTO(array $data): HealthDTO
    {
        return new HealthDTO(
            database:        $data['database'],
            disk_free_gb:    $data['disk_free_gb'],
            disk_total_gb:   $data['disk_total_gb'],
            disk_pct:        $data['disk_pct'],
            php_version:     $data['php_version'],
            laravel_version: $data['laravel_version'],
            environment:     $data['environment'],
            timestamp:       $data['timestamp'],
        );
    }
}
