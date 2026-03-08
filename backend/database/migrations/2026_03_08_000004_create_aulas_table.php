<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aulas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('pabellon_id')
                ->constrained('pabellones')
                ->cascadeOnDelete();
            $table->string('nombre', 20); // A-101, L-02, etc.
            $table->unsignedSmallInteger('capacidad')->default(30);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aulas');
    }
};
