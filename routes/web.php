<?php

use Illuminate\Support\Facades\Route;

Route::get('/', 'MirrorController@index')
    ->name('mirror.index');

Route::post('{command}', 'MirrorController@run')
    ->name('mirror.run');
