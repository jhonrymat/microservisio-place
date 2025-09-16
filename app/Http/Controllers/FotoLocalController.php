<?php

// app/Http/Controllers/FotoLocalController.php
namespace App\Http\Controllers;

use App\Models\FotoLocal;
use Illuminate\Support\Facades\Storage;

class FotoLocalController extends Controller
{
    public function show(int $id)
    {
        $f = FotoLocal::findOrFail($id);

        // Si 'path' es URL absoluta, redirige:
        if (preg_match('~^https?://~', $f->path)) {
            return redirect()->away($f->path);
        }

        if (!Storage::disk('local')->exists($f->path))
            abort(404);

        $contents = Storage::disk('local')->get($f->path);
        $mime = $f->mime ?: 'image/jpeg';
        return response($contents, 200)->header('Content-Type', $mime)
            ->header('Cache-Control', 'public, max-age=604800'); // 7 días
    }
}

