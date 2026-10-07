<?php

use App\Http\Controllers\AdmissionTicketPrintController;
use App\Http\Controllers\ContractPrintController;
use App\Http\Controllers\CustomerApp\AuthController as CustomerAppAuthController;
use App\Http\Controllers\CustomerApp\BookingController as CustomerAppBookingController;
use App\Http\Controllers\CustomerApp\ProfileController as CustomerAppProfileController;
use App\Http\Controllers\CustomerApp\SupportTicketController as CustomerAppSupportTicketController;
use App\Http\Controllers\CustomerApp\TicketController as CustomerAppTicketController;
use App\Http\Controllers\CustomerApp\WalletController as CustomerAppWalletController;
use App\Http\Controllers\CustomerAppController;
use App\Http\Controllers\DocumentDownloadController;
use App\Http\Controllers\FormSubmissionFileController;
use App\Http\Controllers\FormSubmissionPrintController;
use App\Http\Controllers\GateOfflineSyncController;
use App\Http\Controllers\MessageAttachmentController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\ReportPrintController;
use App\Http\Controllers\Seo\GoogleOAuthController;
use App\Http\Controllers\SeoPeriodicReportPrintController;
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

    // مرکز سئو: Search Console OAuth. The callback has no {project}
    // route parameter - Google redirects here with only ?state=&code=,
    // so GoogleOAuthController::callback() recovers the project id from
    // the state value it itself generated in redirect().
    Route::get('/seo/google/redirect/{project}', [GoogleOAuthController::class, 'redirect'])
        ->name('seo.google.redirect');
    Route::get('/seo/google/callback', [GoogleOAuthController::class, 'callback'])
        ->name('seo.google.callback');
    Route::get('/seo-periodic-reports/{seoPeriodicReport}/print', [SeoPeriodicReportPrintController::class, 'show'])
        ->name('seo-periodic-reports.print');
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

// Customer-app auth (see config/auth.php's "customer" guard and
// App\Http\Controllers\CustomerApp\AuthController) - deliberately its own
// route group, never the staff 'auth' middleware group above: a customer
// session must never satisfy an admin-panel auth check, and vice versa.
Route::prefix('app/auth')->name('customer-app.auth.')->group(function () {
    Route::get('/login', [CustomerAppAuthController::class, 'showLogin'])
        ->middleware('guest:customer')->name('login');
    Route::post('/otp/request', [CustomerAppAuthController::class, 'requestOtp'])
        ->middleware(['guest:customer', 'throttle:customer-otp'])->name('otp.request');
    Route::post('/otp/verify', [CustomerAppAuthController::class, 'verifyOtp'])
        ->middleware('guest:customer')->name('otp.verify');
    Route::post('/register', [CustomerAppAuthController::class, 'register'])
        ->middleware('guest:customer')->name('register');
    Route::post('/login', [CustomerAppAuthController::class, 'loginWithPassword'])
        ->middleware('guest:customer')->name('login.attempt');
    Route::get('/google/redirect', [CustomerAppAuthController::class, 'redirectToGoogle'])
        ->middleware('guest:customer')->name('google.redirect');
    Route::get('/google/callback', [CustomerAppAuthController::class, 'googleCallback'])
        ->middleware('guest:customer')->name('google.callback');
    Route::post('/logout', [CustomerAppAuthController::class, 'logout'])
        ->middleware('auth:customer')->name('logout');
});

// Customer-app account features - "سفارش‌های من" etc. - all behind the
// 'customer' guard (see config/auth.php), never the staff 'auth' group.
Route::middleware('auth:customer')->prefix('app')->name('customer-app.')->group(function () {
    Route::get('/profile', [CustomerAppProfileController::class, 'index'])->name('profile');
    Route::get('/bookings', [CustomerAppBookingController::class, 'index'])->name('bookings');
    Route::post('/bookings', [CustomerAppBookingController::class, 'store'])->name('bookings.store');
    Route::get('/wallet', [CustomerAppWalletController::class, 'index'])->name('wallet');
    Route::get('/tickets', [CustomerAppTicketController::class, 'index'])->name('tickets');
    Route::post('/tickets', [CustomerAppTicketController::class, 'store'])->name('tickets.store');
    Route::get('/support', [CustomerAppSupportTicketController::class, 'index'])->name('support');
    Route::post('/support', [CustomerAppSupportTicketController::class, 'store'])->name('support.store');
});
