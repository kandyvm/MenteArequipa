<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE jovenes ALTER COLUMN telefono TYPE TEXT');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE jovenes ALTER COLUMN telefono TYPE VARCHAR(20)');
    }
};