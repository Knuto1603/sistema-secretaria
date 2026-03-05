<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $guardName = 'web';

        // =============================================
        // 1. CREAR PERMISOS
        // =============================================

        // Proyecto A: Registro/Solicitudes
        $pCrearSolicitudes = Permission::firstOrCreate(['name' => 'crear solicitudes', 'guard_name' => $guardName]);
        $pGestionarSolicitudes = Permission::firstOrCreate(['name' => 'gestionar solicitudes', 'guard_name' => $guardName]);

        // Proyecto B: Chatbot
        $pConfigurarBot = Permission::firstOrCreate(['name' => 'configurar chatbot', 'guard_name' => $guardName]);

        // Proyecto C: Analítica
        $pVerAnaliticas = Permission::firstOrCreate(['name' => 'ver analiticas', 'guard_name' => $guardName]);

        // Administración del sistema
        $pGestionarUsuarios = Permission::firstOrCreate(['name' => 'gestionar usuarios', 'guard_name' => $guardName]);
        $pGestionarConfiguracion = Permission::firstOrCreate(['name' => 'gestionar configuracion', 'guard_name' => $guardName]);

        // =============================================
        // 2. CREAR ROLES
        // =============================================

        // Rol Developer (God User - todos los permisos)
        $roleDeveloper = Role::firstOrCreate(['name' => 'developer', 'guard_name' => $guardName]);
        $roleDeveloper->syncPermissions(Permission::all());

        // Rol Admin
        $roleAdmin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => $guardName]);
        $roleAdmin->syncPermissions([
            $pCrearSolicitudes,
            $pGestionarSolicitudes,
            $pConfigurarBot,
            $pVerAnaliticas,
            $pGestionarUsuarios,
            $pGestionarConfiguracion
        ]);

        // Rol Secretaria (Operativo)
        $roleSecretaria = Role::firstOrCreate(['name' => 'secretaria', 'guard_name' => $guardName]);
        $roleSecretaria->syncPermissions([
            $pGestionarSolicitudes,
            $pConfigurarBot
        ]);

        // Rol Secretario Académico (Gestión y Supervisión)
        $roleSecretarioAcademico = Role::firstOrCreate(['name' => 'secretario academico', 'guard_name' => $guardName]);
        $roleSecretarioAcademico->syncPermissions([
            $pGestionarSolicitudes,
            $pConfigurarBot,
            $pVerAnaliticas
        ]);

        // Rol Decano (Alta Dirección)
        $roleDecano = Role::firstOrCreate(['name' => 'decano', 'guard_name' => $guardName]);
        $roleDecano->syncPermissions([
            $pVerAnaliticas,
            $pGestionarSolicitudes
        ]);

        // Rol Estudiante
        $roleEstudiante = Role::firstOrCreate(['name' => 'estudiante', 'guard_name' => $guardName]);
        $roleEstudiante->syncPermissions([$pCrearSolicitudes]);

        // =============================================
        // 3. CREAR USUARIO DEVELOPER (GOD USER)
        // =============================================

        $developer = User::updateOrCreate(
            ['username' => 'Knuto'],
            [
                'name' => 'Developer',
                'email' => 'developer@sistema.local',
                'tipo_usuario' => 'developer',
                'username' => 'Knuto',
                'codigo_universitario' => null,
                'password' => Hash::make('admin123'),
                'password_set_at' => now(),
            ]
        );
        $developer->syncRoles([$roleDeveloper]);

        // =============================================
        // 4. CREAR USUARIOS ADMINISTRATIVOS
        // =============================================

        $admin = User::updateOrCreate(
            ['username' => 'admin'],
            [
                'name' => 'Administrador',
                'email' => 'admin@unp.edu.pe',
                'tipo_usuario' => 'administrativo',
                'username' => 'admin',
                'codigo_universitario' => null,
                'password' => Hash::make('password123'),
                'password_set_at' => now(),
            ]
        );
        $admin->syncRoles([$roleAdmin]);

        $decano = User::updateOrCreate(
            ['username' => 'decano'],
            [
                'name' => 'Dr. Juan Pérez López',
                'email' => 'decano@unp.edu.pe',
                'tipo_usuario' => 'administrativo',
                'username' => 'decano',
                'codigo_universitario' => null,
                'password' => Hash::make('password123'),
                'password_set_at' => now(),
            ]
        );
        $decano->syncRoles([$roleDecano]);

        $secAcademico = User::updateOrCreate(
            ['username' => 'sec.academico'],
            [
                'name' => 'Lic. María García Ruiz',
                'email' => 'sec.academico@unp.edu.pe',
                'tipo_usuario' => 'administrativo',
                'username' => 'sec.academico',
                'codigo_universitario' => null,
                'password' => Hash::make('password123'),
                'password_set_at' => now(),
            ]
        );
        $secAcademico->syncRoles([$roleSecretarioAcademico]);

        $secretaria = User::updateOrCreate(
            ['username' => 'secretaria'],
            [
                'name' => 'Ana Torres Mendoza',
                'email' => 'secretaria@unp.edu.pe',
                'tipo_usuario' => 'administrativo',
                'username' => 'secretaria',
                'codigo_universitario' => null,
                'password' => Hash::make('password123'),
                'password_set_at' => now(),
            ]
        );
        $secretaria->syncRoles([$roleSecretaria]);

        $this->command->info('Roles, permisos y usuarios administrativos creados correctamente.');
        $this->command->table(
            ['Usuario', 'Username', 'Rol', 'Password'],
            [
                ['Developer', 'Knuto', 'developer', 'admin123'],
                ['Administrador', 'admin', 'admin', 'password123'],
                ['Decano', 'decano', 'decano', 'password123'],
                ['Sec. Académico', 'sec.academico', 'secretario academico', 'password123'],
                ['Secretaria', 'secretaria', 'secretaria', 'password123'],
            ]
        );
    }
}
