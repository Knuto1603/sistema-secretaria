<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('generaciones_modificaciones', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('periodo_id')->constrained('periodos')->restrictOnDelete();
            $table->date('fecha_desde');
            $table->date('fecha_hasta');
            $table->string('numero_oficio', 100);
            $table->foreignUuid('generado_por')->constrained('users')->restrictOnDelete();
            $table->timestamp('generado_at');
            $table->unsignedInteger('total_documentos')->default(0);
            $table->timestamps();

            $table->index('periodo_id');
            $table->index('generado_por');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generaciones_modificaciones');
    }
};
