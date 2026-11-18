<?php

use Illuminate\Support\Facades\Route;

Route::get('/{path?}', function () {
    $spa = public_path('spa.html');

    if (! is_file($spa)) {
        return view('welcome');
    }

    return response()->file($spa, ['Cache-Control' => 'no-cache']);
})->where('path', '(?!api/|sanctum/|up$|storage/).*');
