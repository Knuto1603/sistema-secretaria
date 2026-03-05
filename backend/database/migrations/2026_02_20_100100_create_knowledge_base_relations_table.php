<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_base_relations', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('source_id')->constrained('knowledge_base')->cascadeOnDelete();
            $table->foreignUuid('target_id')->constrained('knowledge_base')->cascadeOnDelete();
            $table->enum('tipo', ['relacionado', 'prerrequisito', 'continua_en'])->default('relacionado');
            $table->timestamps();

            $table->unique(['source_id', 'target_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_base_relations');
    }
};
