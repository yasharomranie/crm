<?php

namespace App\Http\Controllers\Api\V1\External;

use App\Http\Controllers\Controller;
use App\Models\DiscountCode;
use App\Models\ExternalApiClient;
use App\Models\TicketVariant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read-only: checks whether a code would be accepted for a given
 * ticket_variant/quantity without consuming any usage or locking the row -
 * the actual, concurrency-safe consumption only ever happens inside
 * TicketIssuer::issue() (via DiscountService::lockAndValidate()) when a
 * ticket is actually purchased. This lets an external storefront show a
 * discount preview before checkout without reserving anything.
 */
class DiscountCodeController extends Controller
{
    public function validateCode(Request $request): JsonResponse
    {
        /** @var ExternalApiClient $client */
        $client = $request->attributes->get('external_api_client');

        if (! $client->hasPermission('discount_code.validate')) {
            return $this->error('FORBIDDEN', 'این اکانت مجاز به اعتبارسنجی کد تخفیف نیست.', 403);
        }

        $data = $request->validate([
            'code' => ['required', 'string', 'max:50'],
            'ticket_variant_id' => ['required', 'integer'],
            'quantity' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'customer_phone' => ['nullable', 'string', 'max:32'],
        ]);

        $variant = TicketVariant::find($data['ticket_variant_id']);

        if (! $variant) {
            return $this->error('NOT_FOUND', 'نوع بلیت یافت نشد.', 404);
        }

        if (! $client->isProductAllowed($variant->product_id)) {
            return $this->error('FORBIDDEN', 'این اکانت مجاز به فروش این محصول نیست.', 403);
        }

        $price = $variant->currentPrice();

        if (! $price) {
            return $this->error('VALIDATION_ERROR', "قیمتی برای «{$variant->name}» تعریف نشده است.", 422);
        }

        $discountCode = DiscountCode::where('code', $data['code'])->first();

        if (! $discountCode) {
            return $this->error('NOT_FOUND', 'کد تخفیف نامعتبر است.', 404);
        }

        $quantity = $data['quantity'] ?? 1;
        $subtotal = $price->amount * $quantity;

        $error = $discountCode->eligibilityError($variant, $subtotal, $data['customer_phone'] ?? null);

        if ($error) {
            return response()->json([
                'valid' => false,
                'reason' => $error,
            ]);
        }

        $discountAmount = $discountCode->calculateDiscount($subtotal);

        return response()->json([
            'valid' => true,
            'code' => $discountCode->code,
            'subtotal' => $subtotal,
            'discount_amount' => $discountAmount,
            'final_amount' => $subtotal - $discountAmount,
        ]);
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return response()->json(['error' => ['code' => $code, 'message' => $message]], $status);
    }
}
