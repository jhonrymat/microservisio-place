<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MapaCategoria extends Model
{
    use HasFactory;

    // Definir el nombre de la tabla
    protected $table = 'mapa_categoria';

    // Campos que se pueden asignar masivamente
    protected $fillable = [
        'tipo_google',
        'categoria_plataforma',
    ];
}
