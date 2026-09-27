<?php

namespace App\Http\Controllers\Api\V1\External;

use App\Enums\WebhookEventType;
use App\Http\Controllers\Controller;
use App\Models\ExternalApiClient;
use App\Models\WebhookSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Lets an external system manage its own webhook subscriptions (which
 * events it wants, and where to receive them) without needing a person to
 * go through the admin panel - the signing secret is only ever returned
 * once, at registration time, exactly like the API client's own secret.
 */
class WebhookSubscriptionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var ExternalApiClient $client */
        $client = $request->attributes->get('external_api_client');

        if (! $client->hasPermission('webhook.manage')) {
            return $this->error('FORBIDDEN', 'این اکانت مجاز به مدیریت وب‌هوک نیست.', 403);
        }

        $subscriptions = WebhookSubscription::where('external_api_client_id', $client->id)->get();

        return response()->json([
            'data' => $subscriptions->map(fn (WebhookSubscription $subscription) => $this->transform($subscription)),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        /** @var ExternalApiClient $client */
        $client = $request->attributes->get('external_api_client');

        if (! $client->hasPermission('webhook.manage')) {
            return $this->error('FORBIDDEN', 'این اکانت مجاز به مدیریت وب‌هوک نیست.', 403);
        }

        $data = $request->validate([
            'event_type' => ['required', 'string', 'in:'.implode(',', array_column(WebhookEventType::cases(), 'value'))],
            'target_url' => ['required', 'url', 'max:2048'],
        ]);

        $signingSecret = Str::random(40);

        $subscription = WebhookSubscription::create([
            'external_api_client_id' => $client->id,
            'event_type' => WebhookEventType::from($data['event_type']),
            'target_url' => $data['target_url'],
            'signing_secret' => $signingSecret,
            'is_active' => true,
        ]);

        return response()->json([
            ...$this->transform($subscription),
            'signing_secret' => $signingSecret,
        ], 201);
    }

    public function destroy(Request $request, int $webhookSubscription): JsonResponse
    {
        /** @var ExternalApiClient $client */
        $client = $request->attributes->get('external_api_client');

        if (! $client->hasPermission('webhook.manage')) {
            return $this->error('FORBIDDEN', 'این اکانت مجاز به مدیریت وب‌هوک نیست.', 403);
        }

        $subscription = WebhookSubscription::find($webhookSubscription);

        if (! $subscription || $subscription->external_api_client_id !== $client->id) {
            return $this->error('NOT_FOUND', 'اشتراک وب‌هوکی با این شناسه یافت نشد.', 404);
        }

        $subscription->delete();

        return response()->json(status: 204);
    }

    /**
     * @return array<string, mixed>
     */
    private function transform(WebhookSubscription $subscription): array
    {
        return [
            'webhook_subscription_id' => $subscription->id,
            'event_type' => $subscription->event_type->value,
            'target_url' => $subscription->target_url,
            'is_active' => $subscription->is_active,
            'last_triggered_at' => $subscription->last_triggered_at?->toIso8601String(),
            'created_at' => $subscription->created_at->toIso8601String(),
        ];
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return response()->json(['error' => ['code' => $code, 'message' => $message]], $status);
    }
}
