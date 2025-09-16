<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FotoLocalController;
use App\Http\Controllers\MediaController;

Route::get('/', function () {
    return view('welcome');
});


Route::get('/media/local/{id}', [FotoLocalController::class, 'show'])->name('media.local');
Route::get('/media/local/{id}', [MediaController::class, 'local'])
    ->whereNumber('id')
    ->name('media.local');

