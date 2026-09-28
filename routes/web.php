<?php

use App\Http\Controllers\AdmissionTicketPrintController;
use App\Http\Controllers\ContractPrintController;
use App\Http\Controllers\CustomerAppController;
use App\Http\Controllers\DocumentDownloadController;
use App\Http\Controllers\FormSubmissionFileController;
use App\Http\Controllers\FormSubmissionPrintController;
use App\Http\Controllers\GateOfflineSyncController;
use App\Http\Controllers\MessageAttachmentController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\ReportPrintController;
use App\Http\Controllers\VoiceMessageController;
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
    Route::get('/form-submissions/{submission}/export-word', [FormSubmissionPrintController::class, 'exportWord'])
        ->name('form-submissions.export-word');
    Route::get('/form-submissions/{submission}/files/{value}', [FormSubmissionFileController::class, 'show'])
        ->name('form-submissions.files.download');
    Route::get('/reports/print/{token}', [ReportPrintController::class, 'show'])
        ->name('reports.print');
    Route::post('/push-subscriptions', [PushSubscriptionController::class, 'store'])
        ->name('push-subscriptions.store');
    Route::delete('/push-subscriptions', [PushSubscriptionController::class, 'destroy'])
        ->name('push-subscriptions.destroy');
    Route::get('/messages/{message}/attachment', [MessageAttachmentController::class, 'show'])
        ->name('messages.attachment');
    Route::post('/messages/voice-note', [VoiceMessageController::class, 'store'])
        ->name('messages.voice-note.store');
    Route::get('/admission-tickets/{ticket}/print', [AdmissionTicketPrintController::class, 'show'])
        ->name('admission-tickets.print');
    Route::get('/gate-scanner/{gate}/sync-package', [GateOfflineSyncController::class, 'pullPackage'])
        ->name('gate-scanner.sync-package');
    Route::post('/gate-scanner/{gate}/sync-scans', [GateOfflineSyncController::class, 'pushScans'])
        ->name('gate-scanner.sync-scans');
});

// Screenshot-illustrated walkthrough of the panel, linked from the admin
// user menu (see AdminPanelProvider) - a plain static view, not a Filament
// page, so it renders as its own full document instead of being wrapped in
// the panel's chrome. Kept out of the 'auth' group above: that middleware's
// default guest redirect targets a route named 'login', which doesn't
// exist in this app (Filament's own is 'filament.admin.auth.login'), so it
// 500s instead of redirecting - checking manually here sidesteps that.
Route::get('/system-guide', function () {
    return auth()->check()
        ? view('system-guide')
        : redirect()->route('filament.admin.auth.login');
})->name('system-guide');

// The REST API reference used to live on its own page here - now folded
// into system-guide's own "REST API برای سامانه‌های بیرونی" chapter, so
// any bookmarked/shared link still lands somewhere useful instead of 404ing.
Route::redirect('/api-docs', '/system-guide#ch-13');

// The public, installable گنجنامه customer app - its own PWA identity
// (separate manifest/service worker from the staff admin one; see
// resources/views/partials/customer-app-pwa-head.blade.php), no login.
// Content is fully admin-managed via the "وب‌اپ مشتریان" Filament
// resources (WebAppBanner/WebAppSection/WebAppSectionItem).
Route::get('/app', [CustomerAppController::class, 'index'])->name('customer-app');
