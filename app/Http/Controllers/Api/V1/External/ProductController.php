<?php

namespace App\Http\Controllers\Api\V1\External;

use App\Http\Controllers\Controller;
use App\Models\ExternalApiClient;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var ExternalApiClient $client */
        $client = $request->attributes->get('external_api_client');

        $products = Product::query()
            ->where('is_active', true)
            ->when(
                $client->allowedProducts()->exists(),
                fn ($query) => $query->whereIn('id', $client->allowedProducts()->pluck('products.id')),
            )
            ->with(['ticketVariants' => fn ($query) => $query->where('is_active', true)])
            ->get();

        return response()->json([
            'data' => $products->map(fn (Product $product) => [
                'id' => $product->id,
                'code' => $product->code,
                'name' => $product->name,
                'status' => $product->is_active ? 'active' : 'inactive',
                'variants' => $product->ticketVariants->map(fn ($variant) => [
                    'id' => $variant->id,
                    'code' => $variant->code,
                    'name' => $variant->name,
                ]),
            ]),
        ]);
    }
}
