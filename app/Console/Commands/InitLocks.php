<?php

// app/Console/Commands/InitLocks.php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\BloqueoCampo;

class InitLocks extends Command
{
    protected $signature = 'ms:init-locks
                            {idListing : ID del listing en la plataforma}
                            {--all : Bloquear TODOS los campos disponibles}
                            {--minimal : Solo bloquear campos críticos}
                            {--custom=* : Campos específicos a bloquear}';

    protected $description = 'Crea bloqueos por defecto para un listing';

    public function handle()
    {
        $id = (int) $this->argument('idListing');

        // Verificar que el listing existe
        $listing = \App\Models\NegocioPlataforma::find($id);
        if (!$listing) {
            $this->error("❌ El listing #{$id} no existe en la plataforma");
            return self::FAILURE;
        }

        $this->info("🔍 Listing encontrado: {$listing->name}");

        // Determinar qué campos bloquear según las opciones
        if ($this->option('all')) {
            $campos = $this->getCamposCompletos();
            $this->warn('⚠️  Modo: TODOS los campos');
        } elseif ($this->option('minimal')) {
            $campos = $this->getCamposMinimos();
            $this->info('✅ Modo: Campos críticos únicamente');
        } elseif ($this->option('custom')) {
            $campos = $this->option('custom');
            $this->info('🎯 Modo: Campos personalizados');
        } else {
            $campos = $this->getCamposPorDefecto();
            $this->info('📋 Modo: Configuración por defecto');
        }

        $this->info("📌 Se bloquearán " . count($campos) . " campos");

        if ($this->confirm('¿Continuar?', true)) {
            $creados = 0;
            $actualizados = 0;

            foreach ($campos as $campo) {
                $bloqueo = BloqueoCampo::updateOrCreate(
                    ['id_negocio_plataforma' => $id, 'campo' => $campo],
                    ['bloqueado' => true]
                );

                if ($bloqueo->wasRecentlyCreated) {
                    $creados++;
                } else {
                    $actualizados++;
                }
            }

            $this->info("✅ Bloqueos aplicados:");
            $this->line("   • Creados: {$creados}");
            $this->line("   • Actualizados: {$actualizados}");
            $this->newLine();
            $this->info("🎉 Listing #{$id} configurado correctamente");

            return self::SUCCESS;
        }

        $this->warn('❌ Operación cancelada');
        return self::FAILURE;
    }

    protected function getCamposPorDefecto(): array
    {
        // Configuración estándar: proteger contenido editorial y media
        return [
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
            'certifications',
        ];
    }

    protected function getCamposMinimos(): array
    {
        // Solo lo más crítico que raramente debe sincronizarse
        return [
            'description',
            'photos',
            'listing_thumbnail',
            'listing_cover',
            'seo_meta_tags',
            'meta_description',
        ];
    }

    protected function getCamposCompletos(): array
    {
        // Bloquear absolutamente todo (útil para listings 100% manuales)
        return [
            'name',
            'address',
            'phone',
            'email',
            'website',
            'latitude',
            'longitude',
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
        ];
    }
}

// Ejemplos de uso:
// php artisan ms:init-locks 145                    # Bloqueos por defecto
// php artisan ms:init-locks 145 --minimal          # Solo críticos
// php artisan ms:init-locks 145 --all              # Todos los campos
// php artisan ms:init-locks 145 --custom=phone --custom=email  # Específicos
