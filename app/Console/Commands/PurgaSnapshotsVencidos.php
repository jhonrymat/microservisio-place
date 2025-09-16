<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class PurgaSnapshotsVencidos extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:purga-snapshots-vencidos';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    // app/Console/Commands/PurgaSnapshotsVencidos.php
    public function handle()
    {
        \DB::transaction(function () {
            $vencidos = \App\Models\InstantaneaLugar::where('fecha_expiracion_ttl', '<', now())
                ->leftJoin('lugar_vinculado', 'lugar_vinculado.id_lugar', '=', 'instantanea_lugar.id_lugar')
                ->whereNull('lugar_vinculado.id_lugar')
                ->select('instantanea_lugar.id_lugar')
                ->pluck('id_lugar');

            if ($vencidos->isNotEmpty()) {
                \App\Models\FotoLocal::whereIn('place_id', $vencidos)->delete();
                \App\Models\InstantaneaLugar::whereIn('id_lugar', $vencidos)->delete();
            }
        });

        $this->info('Purgado completado.');
    }

}
