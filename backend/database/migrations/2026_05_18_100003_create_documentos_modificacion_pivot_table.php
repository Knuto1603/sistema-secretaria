<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documentos_modificacion_pivot', function (Blueprint $table) {
            $table->foreignUuid('documento_id')
                ->constrained('documentos_modificacion_area')
                ->cascadeOnDelete();
            $table->foreignUuid('modificacion_id')
                ->constrained('modificaciones_programacion')
                ->cascadeOnDelete();

            $table->primary(['documento_id', 'modificacion_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documentos_modificacion_pivot');
    }
};
