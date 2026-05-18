<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documentos_modificacion_area', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('generacion_id')
                ->constrained('generaciones_modificaciones')
                ->cascadeOnDelete();
            $table->foreignUuid('area_id')->constrained('areas')->restrictOnDelete();
            $table->enum('tipo_documento', ['cierre', 'cierre_apertura', 'fusion', 'cambio_aula']);
            $table->string('nombre_archivo', 255);
            $table->string('ruta', 500);
            $table->unsignedInteger('modificaciones_count')->default(0);
            $table->timestamps();

            $table->index('generacion_id');
            $table->index('area_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documentos_modificacion_area');
    }
};
