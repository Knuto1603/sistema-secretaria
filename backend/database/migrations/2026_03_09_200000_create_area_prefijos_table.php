<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('area_prefijos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('area_id')->constrained('areas')->cascadeOnDelete();
            $table->string('prefijo', 10)->unique(); // Ej: "SI", "MA", "AL"
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('area_prefijos');
    }
};
