<?php

use App\Http\Controllers\RcaCalculatorController;
use Illuminate\Support\Facades\Route;

Route::get('/', [RcaCalculatorController::class, 'index'])->name('rca.index');
Route::post('/oferte', [RcaCalculatorController::class, 'offer'])->name('rca.offer');
Route::post('/calculatii/{calculation}/polita', [RcaCalculatorController::class, 'policy'])->name('rca.policy');
Route::get('/calculatii/{calculation}/pdf/{type}', [RcaCalculatorController::class, 'pdf'])->name('rca.pdf');
