<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consentimientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('joven_id')->constrained('jovenes')->restrictOnDelete();
            $table->string('version_texto', 20);
            $table->string('user_agent', 255);
            $table->timestampTz('aceptado_en')->useCurrent();
        });

        DB::statement("ALTER TABLE consentimientos ADD COLUMN ip INET NOT NULL");
    }

    public function down(): void
    {
        Schema::dropIfExists('consentimientos');
    }
};