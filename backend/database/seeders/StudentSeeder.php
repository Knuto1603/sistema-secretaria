<?php

namespace Database\Seeders;

use App\Models\Escuela;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class StudentSeeder extends Seeder
{
    public function run(): void
    {
        $roleEstudiante = Role::where('name', 'estudiante')->first();

        if (!$roleEstudiante) {
            $this->command->error('El rol "estudiante" no existe. Ejecuta primero RolesAndPermissionsSeeder.');
            return;
        }

        // Estudiantes de prueba de diferentes escuelas
        // Formato código: FFEGGGGNNN (FF=Facultad, E=Escuela, GGGG=Año, NNN=Correlativo)
        // Escuelas: 0=Industrial, 1=Informática, 2=Mecatrónica, 3=Agroindustrial
        $estudiantes = [
            // Informática (05-1-XXXX-XXX)
            [
                'codigo' => '0512021001',
                'name' => 'Carlos Alberto Ramírez Soto',
                'has_password' => true,
            ],
            [
                'codigo' => '0512022015',
                'name' => 'María Fernanda López García',
                'has_password' => true,
            ],
            [
                'codigo' => '0512023042',
                'name' => 'José Luis Martínez Pérez',
                'has_password' => false, // Para probar flujo OTP
            ],
            // Industrial (05-0-XXXX-XXX)
            [
                'codigo' => '0502022008',
                'name' => 'Ana Lucía Hernández Cruz',
                'has_password' => false,
            ],
            // Mecatrónica (05-2-XXXX-XXX)
            [
                'codigo' => '0522021033',
                'name' => 'Pedro Miguel Sánchez Ruiz',
                'has_password' => false,
            ],
            // Agroindustrial (05-3-XXXX-XXX)
            [
                'codigo' => '0532023011',
                'name' => 'Laura Valentina Torres Mendoza',
                'has_password' => false,
            ],
        ];

        foreach ($estudiantes as $data) {
            $email = User::generarEmailEstudiante($data['codigo']);

            $estudiante = User::updateOrCreate(
                ['codigo_universitario' => $data['codigo']],
                [
                    'name' => $data['name'],
                    'email' => $email,
                    'tipo_usuario' => 'estudiante',
                    'username' => null,
                    'codigo_universitario' => $data['codigo'],
                    'password' => $data['has_password'] ? bcrypt('estudiante123') : null,
                    'password_set_at' => $data['has_password'] ? now() : null,
                ]
            );

            // Asignar escuela y año de ingreso desde el código
            $estudiante->asignarDatosDesdeCodigoUniversitario();

            $estudiante->syncRoles([$roleEstudiante]);
        }

        $this->command->info('Estudiantes de prueba creados correctamente.');
        $this->command->newLine();

        // Mostrar tabla con información
        $this->command->table(
            ['Código', 'Nombre', 'Escuela', 'Año', '¿Password?'],
            collect($estudiantes)->map(function($e) {
                $datos = User::parsearCodigoUniversitario($e['codigo']);
                $escuela = Escuela::findByCodigo($datos['escuela']);
                return [
                    $e['codigo'],
                    $e['name'],
                    $escuela?->nombre_corto ?? '-',
                    $datos['anio_ingreso'],
                    $e['has_password'] ? 'estudiante123' : 'Requiere OTP',
                ];
            })->toArray()
        );

        $this->command->newLine();
        $this->command->info('Para probar el flujo OTP, usa los estudiantes SIN password.');
        $this->command->info('Los emails OTP se guardan en: storage/logs/laravel.log');
    }
}
