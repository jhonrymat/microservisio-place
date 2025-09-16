<?php
// app/Console/Commands/InitLocks.php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\BloqueoCampo;

class InitLocks extends Command
{
    protected $signature = 'ms:init-locks {idListing}';
    protected $description = 'Crea bloqueos por defecto para un listing';

    public function handle()
    {
        $id = (int) $this->argument('idListing');

        $protegidos = [
            'categories',
            'description',
            'amenities',
            'photos',
            'video_url',
            'video_provider',
            'tags',
            'social',
            'seo_meta_tags',
            'meta_description',
            'listing_type',
            'listing_thumbnail',
            'listing_cover',
            'opened_minutes',
            'closed_minutes',
            'certifications',
            'price_range',
            'owner_name',
            'owner_phone',
            'owner_email',
            'owner_notes'
        ];

        foreach ($protegidos as $campo) {
            BloqueoCampo::updateOrCreate(
                ['id_negocio_plataforma' => $id, 'campo' => $campo],
                ['bloqueado' => true]
            );
        }

        $this->info("Locks creados para listing {$id}");
    }
}

