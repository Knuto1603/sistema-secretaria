<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aulas', function (Blueprint $table) {
            $table->dropForeign(['pabellon_id']);
            $table->uuid('pabellon_id')->nullable()->change();
            $table->foreign('pabellon_id')
                ->references('id')->on('pabellones')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('aulas', function (Blueprint $table) {
            $table->dropForeign(['pabellon_id']);
            $table->uuid('pabellon_id')->nullable(false)->change();
            $table->foreign('pabellon_id')
                ->references('id')->on('pabellones')
                ->cascadeOnDelete();
        });
    }
};
