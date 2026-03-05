<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class AdminSeed extends Seeder
{
    public function run(): void{
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        $guardName = 'web';

        $pCrearSolicitudes = Permission::firstOrCreate(['name' => 'crear solicitudes', 'guard_name' => $guardName]);
        $pGestionarSolicitudes = Permission::firstOrCreate(['name' => 'gestionar solicitudes', 'guard_name' => $guardName]);

        // Proyecto B: Chatbot
        $pConfigurarBot = Permission::firstOrCreate(['name' => 'configurar chatbot', 'guard_name' => $guardName]);

        // Proyecto C: Analítica
        $pVerAnaliticas = Permission::firstOrCreate(['name' => 'ver analiticas', 'guard_name' => $guardName]);


        // Definimos el guard por defecto para evitar confusiones entre web y api

        // Rol Admin (Tiene todo)
        $roleAdmin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => $guardName]);
        $roleAdmin->syncPermissions([
            $pCrearSolicitudes,
            $pGestionarSolicitudes,
            $pConfigurarBot,
            $pVerAnaliticas
        ]);

        $admin = User::updateOrCreate(
            ['email' => 'admin@universidad.edu'],
            [
                'name' => 'Administrador',
                'password' => Hash::make('password123'),
            ]
        );
        $admin->assignRole($roleAdmin);
    }

}
