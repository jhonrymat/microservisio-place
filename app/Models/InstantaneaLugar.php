<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InstantaneaLugar extends Model
{
    use HasFactory;

    // Definir el nombre de la tabla
    protected $table = 'instantanea_lugar';

    // Campos que se pueden asignar masivamente
    protected $fillable = [
        'id_lugar',
        'carga',
        'fecha_fetched',
        'fecha_expiracion_ttl',
        'batch_token',
        'search_key',
    ];

    public $timestamps = true;

    // Definir la relación con LugarVinculado (no es necesario por claves foráneas, se gestiona en la lógica)
    public function lugarVinculado()
    {
        return $this->hasOne(LugarVinculado::class, 'id_lugar', 'id_lugar');
    }
}
