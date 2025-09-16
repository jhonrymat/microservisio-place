<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BloqueoCampo extends Model
{
    use HasFactory;

    // Definir el nombre de la tabla
    protected $table = 'bloqueo_campo';

    // Campos que se pueden asignar masivamente
    protected $fillable = [
        'id_negocio_plataforma',
        'campo',
        'bloqueado',
    ];

    // Relación con el negocio de la plataforma
    public function negocioPlataforma()
    {
        return $this->belongsTo(NegocioPlataforma::class, 'id_negocio_plataforma');
    }
}
