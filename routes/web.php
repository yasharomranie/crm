<?php

use App\Http\Controllers\ContractPrintController;
use App\Http\Controllers\DocumentDownloadController;
use App\Http\Controllers\FormSubmissionFileController;
use App\Http\Controllers\FormSubmissionPrintController;
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
    Route::get('/contracts/{contract}/export-word', [ContractPrintController::class, 'exportWord'])
        ->name('contracts.export-word');
    Route::get('/form-submissions/{submission}/print', [FormSubmissionPrintController::class, 'show'])
        ->name('form-submissions.print');
    Route::get('/form-submissions/{submission}/files/{value}', [FormSubmissionFileController::class, 'show'])
        ->name('form-submissions.files.download');
});
