<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ajuste extends Model
{
    use HasFactory;

    // Definir el nombre de la tabla
    protected $table = 'ajustes';

    // Campos que se pueden asignar masivamente
    protected $fillable = [
        'clave',
        'valor',
    ];

    // Clave primaria personalizada
    protected $primaryKey = 'clave';
}
