<?php

use App\Http\Controllers\TranslationController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});


Route::get('test', [TranslationController::class, 'translate']);


Route::get('language/list', [TranslationController::class, 'listLanguages']);
Route::post('translate', [TranslationController::class, 'translate']);
