<?php

namespace App\Http\Controllers;

use App\Models\FotoLocal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class MediaController extends Controller
{
    public function local(int $id)
    {
        $foto = FotoLocal::findOrFail($id);

        // Archivo en storage/app/{path}
        $path = $foto->path;
        if (! Storage::disk('public')->exists($path)) {
            abort(404);
        }

        $mime = $foto->mime ?: 'image/jpeg';
        $contents = Storage::disk('public')->get($path);

        return response($contents, Response::HTTP_OK, [
            'Content-Type'  => $mime,
            'Cache-Control' => 'public, max-age=604800', // 7 días
        ]);
    }
}
