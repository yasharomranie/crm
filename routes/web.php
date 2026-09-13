<?php

use App\Http\Controllers\ContractPrintController;
use App\Http\Controllers\DocumentDownloadController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('auth')->group(function () {
    Route::get('/documents/{document}/download', [DocumentDownloadController::class, 'current'])
        ->name('documents.download');
    Route::get('/document-versions/{version}/download', [DocumentDownloadController::class, 'version'])
        ->name('document-versions.download');
    Route::get('/contracts/{contract}/print', [ContractPrintController::class, 'show'])
        ->name('contracts.print');
});
