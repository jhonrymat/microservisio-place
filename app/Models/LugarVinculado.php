<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LugarVinculado extends Model
{
    use HasFactory;

    // Definir el nombre de la tabla
    protected $table = 'lugar_vinculado';

    // Campos que se pueden asignar masivamente
    protected $fillable = [
        'id_negocio_plataforma',
        'id_lugar',
        'confianza',
        'estrategia_coincidencia',
        'estado',
        'ultima_verificacion',
    ];

    // No necesitamos relaciones foráneas aquí debido a la arquitectura del microservicio
    // Aquí gestionaremos las relaciones a través de la lógica de negocio

    // Métodos de sincronización o lógica adicional pueden ir aquí
}
