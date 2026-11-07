<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Centro extends Model
{
    protected $table = 'centros';
    public $timestamps = false;

    protected $fillable = ['nombre', 'tipo', 'distrito', 'direccion', 'latitud', 'longitud', 'servicios', 'activo'];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function derivaciones()
    {
        return $this->hasMany(Derivacion::class, 'centro_id');
    }
}