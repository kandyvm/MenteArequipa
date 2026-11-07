<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Derivacion extends Model
{
    protected $table = 'derivaciones';
    public $timestamps = false;

    protected $fillable = [
        'solicitud_id', 'centro_id', 'orientador_id',
        'prioridad', 'observaciones', 'estado', 'aceptada_en',
    ];

    protected function casts(): array
    {
        return ['derivada_en' => 'datetime', 'aceptada_en' => 'datetime'];
    }

    public function solicitud()
    {
        return $this->belongsTo(Solicitud::class, 'solicitud_id');
    }

    public function centro()
    {
        return $this->belongsTo(Centro::class, 'centro_id');
    }

    public function orientador()
    {
        return $this->belongsTo(Usuario::class, 'orientador_id');
    }

    public function seguimientos()
    {
        return $this->hasMany(Seguimiento::class, 'derivacion_id');
    }
}