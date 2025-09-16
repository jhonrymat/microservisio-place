<?php
// app/Models/FotoLocal.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FotoLocal extends Model
{
    protected $table = 'fotos_locales';
    public $timestamps = false; // usamos fetched_at

    protected $fillable = [
        'place_id',
        'photo_name',
        'photo_name_hash',
        'path',
        'size_label',
        'mime',
        'width',
        'height',
        'author_name',
        'author_uri',
        'fetched_at',
    ];

    // Helper para URL pública (si sirves por ruta)
    public function publicUrl(): string
    {
        // Si 'path' ya es URL absoluta, retorna tal cual:
        if (preg_match('~^https?://~', $this->path))
            return $this->path;
        // Si guardas en storage/app/photos, podrías exponer por un controlador o storage:link
        return url('/media/local/' . $this->id);
    }
}

