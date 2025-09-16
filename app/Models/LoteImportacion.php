<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LoteImportacion extends Model
{
    use HasFactory;

    // Definir el nombre de la tabla
    protected $table = 'lote_importacion';

    // Campos que se pueden asignar masivamente
    protected $fillable = [
        'nombre_archivo',
        'total_filas',
        'filas_procesadas',
        'estado',
    ];
}
