<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plantillas_modificacion', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->enum('tipo', ['cierre', 'cierre_apertura', 'fusion', 'cambio_aula'])->unique();
            $table->string('nombre_archivo', 255);
            $table->string('ruta', 500);
            $table->foreignUuid('subido_por')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plantillas_modificacion');
    }
};
