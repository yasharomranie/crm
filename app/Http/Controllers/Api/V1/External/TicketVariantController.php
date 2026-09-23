<?php

namespace App\Http\Controllers\Api\V1\External;

use App\Http\Controllers\Controller;
use App\Models\ExternalApiClient;
use App\Models\TicketVariant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketVariantController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var ExternalApiClient $client */
        $client = $request->attributes->get('external_api_client');

        $variants = TicketVariant::query()
            ->where('is_active', true)
            ->when($request->integer('product_id'), fn ($query, $productId) => $query->where('product_id', $productId))
            ->when(
                $client->allowedProducts()->exists(),
                fn ($query) => $query->whereIn('product_id', $client->allowedProducts()->pluck('products.id')),
            )
            ->get();

        return response()->json([
            'data' => $variants->map(fn (TicketVariant $variant) => [
                'variant_id' => $variant->id,
                'product_id' => $variant->product_id,
                'name' => $variant->name,
                'code' => $variant->code,
                'direction' => $variant->direction?->value,
                'allowed_uses' => $variant->allowed_uses,
                // The current price is informational only - the server
                // always recomputes the real charge at ticket-creation
                // time (section 107/109), so a client can never submit
                // its own price and have it accepted.
                'price' => $variant->currentPrice()?->amount,
                'status' => $variant->is_active ? 'active' : 'inactive',
            ]),
        ]);
    }
}
