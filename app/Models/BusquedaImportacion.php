<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class BusquedaImportacion extends Model
{
    use HasFactory;

    protected $table = 'busqueda_importacion';

    protected $fillable = [
        'nombre',
        'ciudad',
        'lat',
        'lng',
        'page_size',
        'usar_restriccion',
        'batch_token',
        'search_key',
        'estado',
        'candidatos_encontrados',
        'mensaje_error',
        'fecha_ejecutada',
    ];

    protected $casts = [
        'lat' => 'decimal:7',
        'lng' => 'decimal:7',
        'usar_restriccion' => 'boolean',
        'fecha_ejecutada' => 'datetime',
    ];

    // Relación con los resultados (InstantaneaLugar)
    public function resultados()
    {
        return $this->hasMany(InstantaneaLugar::class, 'batch_token', 'batch_token');
    }

    // Generar la clave de búsqueda legible
    public function generarSearchKey(): string
    {
        return $this->ciudad ? "{$this->nombre}, {$this->ciudad}" : $this->nombre;
    }

    // Generar batch token si no existe
    public function generarBatchToken(): string
    {
        if (!$this->batch_token) {
            $this->batch_token = (string) Str::uuid();
            $this->save();
        }
        return $this->batch_token;
    }

    // Scope para búsquedas pendientes
    public function scopePendientes($query)
    {
        return $query->where('estado', 'pendiente');
    }

    // Scope para búsquedas completadas
    public function scopeCompletadas($query)
    {
        return $query->where('estado', 'completado');
    }
}
