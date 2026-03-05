<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

class SystemSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            [
                'key'         => 'app_name',
                'value'       => 'Sistema Secretaría FII-UNP',
                'type'        => 'string',
                'grupo'       => 'general',
                'descripcion' => 'Nombre de la aplicación',
            ],
            [
                'key'         => 'max_cupo_extra',
                'value'       => '3',
                'type'        => 'integer',
                'grupo'       => 'solicitudes',
                'descripcion' => 'Máximo de solicitudes de cupo extra por estudiante',
            ],
            [
                'key'         => 'otp_expiry_mins',
                'value'       => '30',
                'type'        => 'integer',
                'grupo'       => 'auth',
                'descripcion' => 'Minutos de validez del OTP',
            ],
            [
                'key'         => 'max_otps_per_hour',
                'value'       => '3',
                'type'        => 'integer',
                'grupo'       => 'auth',
                'descripcion' => 'Máximo de OTPs que puede solicitar un estudiante por hora',
            ],
            [
                'key'         => 'maintenance_mode',
                'value'       => 'false',
                'type'        => 'boolean',
                'grupo'       => 'sistema',
                'descripcion' => 'Activa el modo mantenimiento del sistema',
            ],
            [
                'key'         => 'decano_nombre',
                'value'       => '',
                'type'        => 'string',
                'grupo'       => 'autoridades',
                'descripcion' => 'Nombre completo del Decano de la FII-UNP',
            ],
            [
                'key'         => 'secretario_academico_nombre',
                'value'       => '',
                'type'        => 'string',
                'grupo'       => 'autoridades',
                'descripcion' => 'Nombre completo del Secretario Académico de la FII-UNP',
            ],
        ];

        SystemSetting::upsert($settings, ['key'], ['value', 'type', 'grupo', 'descripcion']);

        $this->command->info('  System settings seeded.');
    }
}
