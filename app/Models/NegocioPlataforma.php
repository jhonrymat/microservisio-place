<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NegocioPlataforma extends Model
{
    use HasFactory;

    // Definir la conexión con la base de datos de la plataforma
    protected $connection = 'platform';  // Conexión secundaria a la base de datos de la plataforma

    // Definir el nombre de la tabla
    protected $table = 'listing';

    // Campos que se pueden asignar masivamente
    protected $fillable = [
        'code',
        'name',
        'description',
        'categories',
        'amenities',
        'photos',
        'video_url',
        'video_provider',
        'tags',
        'address',
        'email',
        'phone',
        'website',
        'social',
        'user_id',
        'latitude',
        'longitude',
        'country_id',
        'city_id',
        'status',
        'listing_type',
        'listing_thumbnail',
        'listing_cover',
        'seo_meta_tags',
        'meta_description',
        'date_added',
        'date_modified',
        'is_featured',
        'google_analytics_id',
        'package_expiry_date',
        'state_id',
        'price_range',
        'opened_minutes',
        'closed_minutes',
        'certifications'
    ];

    // Métodos de sincronización y lógica de negocio pueden ir aquí
}
