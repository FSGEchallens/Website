<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    try {
        DB::connection()->getPdo();
        $baseOk = true;
    } catch (\Throwable) {
        $baseOk = false;
    }

    return view('welcome', ['baseOk' => $baseOk]);
});
