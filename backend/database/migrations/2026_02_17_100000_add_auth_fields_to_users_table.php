<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Tipo de usuario: developer (god), administrativo, estudiante
            $table->enum('tipo_usuario', ['developer', 'administrativo', 'estudiante'])
                ->default('estudiante')
                ->after('name');

            // Username para administrativos y developer (único, nullable)
            $table->string('username', 50)->nullable()->unique()->after('tipo_usuario');

            // Código universitario para estudiantes (10 dígitos, único, nullable)
            $table->string('codigo_universitario', 10)->nullable()->unique()->after('username');

            // Fecha en que el estudiante estableció su contraseña (null = nunca)
            $table->timestamp('password_set_at')->nullable()->after('password');
        });

        // Hacer password nullable usando SQL directo (evita necesitar doctrine/dbal)
        DB::statement('ALTER TABLE users MODIFY password VARCHAR(255) NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'tipo_usuario',
                'username',
                'codigo_universitario',
                'password_set_at'
            ]);
        });

        // Revertir password a NOT NULL
        DB::statement('ALTER TABLE users MODIFY password VARCHAR(255) NOT NULL');
    }
};
