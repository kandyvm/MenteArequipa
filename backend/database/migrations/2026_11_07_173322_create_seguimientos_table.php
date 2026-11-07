<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seguimientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('derivacion_id')->constrained('derivaciones')->restrictOnDelete();
            $table->foreignId('autor_id')->constrained('usuarios')->restrictOnDelete();
            $table->string('estado_nuevo', 12);
            $table->text('nota')->nullable();
            $table->timestampTz('creado_en')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seguimientos');
    }
};