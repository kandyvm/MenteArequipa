<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Recurso extends Model
{
    protected $table = 'recursos';
    public $timestamps = false;

    protected $fillable = ['titulo', 'tipo', 'url', 'publicado'];

    protected function casts(): array
    {
        return ['publicado' => 'boolean'];
    }
}