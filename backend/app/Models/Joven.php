<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Joven extends Model
{
    protected $table = 'jovenes';
    public $timestamps = false;

    protected $fillable = ['usuario_id', 'fecha_nacimiento', 'distrito', 'telefono'];

    protected function casts(): array
    {
        return [
            'fecha_nacimiento' => 'date',
            'telefono' => 'encrypted',
        ];
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function solicitudes()
    {
        return $this->hasMany(Solicitud::class, 'joven_id');
    }

    public function consentimientos()
    {
        return $this->hasMany(Consentimiento::class, 'joven_id');
    }
}