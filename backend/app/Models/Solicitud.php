<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Solicitud extends Model
{
    protected $table = 'solicitudes';
    public $timestamps = false;

    protected $fillable = [
        'codigo', 'joven_id', 'motivo', 'descripcion',
        'nivel_riesgo', 'canal', 'estado', 'derivada_113',
    ];

    protected function casts(): array
    {
        return ['derivada_113' => 'boolean', 'creada_en' => 'datetime'];
    }

    public function joven()
    {
        return $this->belongsTo(Joven::class, 'joven_id');
    }

    public function derivaciones()
    {
        return $this->hasMany(Derivacion::class, 'solicitud_id');
    }
}