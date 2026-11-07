<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Seguimiento extends Model
{
    protected $table = 'seguimientos';
    public $timestamps = false;

    protected $fillable = ['derivacion_id', 'autor_id', 'estado_nuevo', 'nota'];

    protected function casts(): array
    {
        return ['creado_en' => 'datetime'];
    }

    public function derivacion()
    {
        return $this->belongsTo(Derivacion::class, 'derivacion_id');
    }
}