<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recursos', function (Blueprint $table) {
            $table->id();
            $table->string('titulo', 150);
            $table->string('tipo', 15);
            $table->string('url', 255);
            $table->boolean('publicado')->default(false);
        });

        DB::statement("ALTER TABLE recursos ADD CONSTRAINT recursos_tipo_check CHECK (tipo IN ('GUIA','ENLACE','PDF'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('recursos');
    }
};