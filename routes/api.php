<?php

use App\Http\Controllers\Api\V1\External\BookingController;
use App\Http\Controllers\Api\V1\External\DealController;
use App\Http\Controllers\Api\V1\External\DiscountCodeController;
use App\Http\Controllers\Api\V1\External\DocumentController;
use App\Http\Controllers\Api\V1\External\FormSubmissionController;
use App\Http\Controllers\Api\V1\External\GateScanController;
use App\Http\Controllers\Api\V1\External\ProductController;
use App\Http\Controllers\Api\V1\External\RefundController;
use App\Http\Controllers\Api\V1\External\RoomAvailabilityController;
use App\Http\Controllers\Api\V1\External\TicketController;
use App\Http\Controllers\Api\V1\External\TicketVariantController;
use App\Http\Controllers\Api\V1\External\WebhookSubscriptionController;
use App\Http\Controllers\Api\V1\WhatsAppWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| WhatsApp Cloud API webhook
|--------------------------------------------------------------------------
|
| Public, called directly by Meta - no API-key auth (Meta has no notion
| of one), gated instead by WhatsAppBusinessSetting::isUsable() and the
| configured verify_token (see WhatsAppWebhookController). GET is Meta's
| one-time handshake when this URL is entered in the developer dashboard;
| POST is every actual incoming-message notification after that.
|
*/
Route::get('/v1/whatsapp/webhook', [WhatsAppWebhookController::class, 'verify']);
Route::post('/v1/whatsapp/webhook', [WhatsAppWebhookController::class, 'receive']);

/*
|--------------------------------------------------------------------------
| External Sales API (POS terminals + online sales integrations)
|--------------------------------------------------------------------------
|
| Authenticated with X-API-Key/X-API-Secret (see
| App\Http\Middleware\AuthenticateExternalApiClient), never a user
| session - this is for third-party systems, not staff. Every route also
| enforces a specific operation permission via external-api.can (section
| 104), on top of the coarser is_active check the auth middleware itself
| does.
|
*/
Route::prefix('v1/external')->middleware('external-api.auth')->group(function () {
    Route::get('/products', [ProductController::class, 'index'])
        ->middleware('external-api.can:product.read');
    Route::get('/ticket-variants', [TicketVariantController::class, 'index'])
        ->middleware('external-api.can:ticket_variant.read');
    Route::post('/tickets', [TicketController::class, 'store']);
    Route::get('/tickets/{ticketNumber}', [TicketController::class, 'show']);
    Route::post('/tickets/{ticketNumber}/reprint', [TicketController::class, 'reprint']);
    Route::post('/tickets/{ticketNumber}/refund', [RefundController::class, 'store']);
    Route::post('/discount-codes/validate', [DiscountCodeController::class, 'validateCode']);
    Route::post('/gates/{gateCode}/scan', [GateScanController::class, 'store']);

    Route::get('/rooms/availability', [RoomAvailabilityController::class, 'index']);
    Route::post('/bookings', [BookingController::class, 'store']);
    Route::get('/bookings/{referenceCode}', [BookingController::class, 'show']);

    Route::post('/deals', [DealController::class, 'store']);
    Route::get('/deals/{id}', [DealController::class, 'show']);

    Route::post('/form-templates/{formTemplateId}/submissions', [FormSubmissionController::class, 'store']);

    Route::post('/documents', [DocumentController::class, 'store']);
    Route::get('/documents/{document}/download', [DocumentController::class, 'download']);

    Route::get('/webhooks', [WebhookSubscriptionController::class, 'index']);
    Route::post('/webhooks', [WebhookSubscriptionController::class, 'store']);
    Route::delete('/webhooks/{webhookSubscription}', [WebhookSubscriptionController::class, 'destroy']);
});
