<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->command->info('=== Iniciando seeders del Sistema Secretaría ===');
        $this->command->newLine();

        // 1. Roles, permisos y usuarios administrativos (incluye Developer)
        $this->call(RolesAndPermissionsSeeder::class);
        $this->command->newLine();

        // 2. Tipos de solicitud
        $this->call(TipoSolicitudSeeder::class);
        $this->command->newLine();

        // 3. Estudiantes de prueba
        $this->call(StudentSeeder::class);
        $this->command->newLine();

        // 4. Configuraciones del sistema
        $this->call(SystemSettingsSeeder::class);
        $this->command->newLine();

        $this->command->info('=== Seeders completados ===');
    }
}
