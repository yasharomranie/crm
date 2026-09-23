<?php

use App\Http\Controllers\Api\V1\External\ProductController;
use App\Http\Controllers\Api\V1\External\TicketController;
use App\Http\Controllers\Api\V1\External\TicketVariantController;
use Illuminate\Support\Facades\Route;

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
});
