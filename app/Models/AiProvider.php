<?php

namespace App\Models;

use App\Exceptions\AiProviderException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

/**
 * A connection to any OpenAI-compatible chat completions API - OpenAI
 * itself, or one of the proxy/reseller services (GapGPT and similar) that
 * exist specifically because they mirror OpenAI's request/response schema
 * on their own base_url. Only one provider is ever "active" at a time;
 * that's the one AiAssistant actually calls.
 */
class AiProvider extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'base_url', 'api_key', 'default_model', 'is_active'];

    /**
     * The token usage the provider reported for the most recent
     * chatCompletion() call on this instance, when it reports any.
     *
     * @var array{prompt_tokens?: int, completion_tokens?: int}|null
     */
    public ?array $lastUsage = null;

    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saved(function (self $provider) {
            if ($provider->is_active) {
                static::query()->whereKeyNot($provider->id)->update(['is_active' => false]);
            }
        });
    }

    public static function active(): ?self
    {
        return static::query()->where('is_active', true)->first();
    }

    /**
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<int, array<string, mixed>>  $tools
     * @param  string|null  $model  overrides $this->default_model for this call only - the
     *                              per-assistant "مدل زبانی" preference (see AiAssistantProfile,
     *                              AiAssistantModelSetting), falling back to the provider's own
     *                              default when the assistant has none set.
     * @return array<string, mixed> the assistant message (content and/or tool_calls)
     */
    public function chatCompletion(array $messages, array $tools = [], ?string $model = null): array
    {
        $payload = [
            'model' => $model ?: $this->default_model,
            'messages' => $messages,
        ];

        if (filled($tools)) {
            $payload['tools'] = $tools;
            $payload['tool_choice'] = 'auto';
        }

        try {
            // A rate limit, a gateway hiccup or a dropped connection is worth
            // one more try before the manager sees an error; a bad key or a
            // rejected request (4xx other than 408/429) is not.
            $response = Http::withToken($this->api_key)
                ->baseUrl(rtrim($this->base_url, '/'))
                ->timeout(90)
                ->retry(2, 800, fn (\Throwable $exception) => $this->isTransientFailure($exception), throw: false)
                ->post('/chat/completions', $payload);
        } catch (ConnectionException) {
            throw AiProviderException::requestFailed('اتصال به سرویس هوش مصنوعی برقرار نشد یا زمان پاسخ‌گویی تمام شد.');
        }

        if ($response->failed()) {
            throw AiProviderException::requestFailed(
                $response->json('error.message') ?? "HTTP {$response->status()}",
            );
        }

        $this->lastUsage = $response->json('usage');

        $message = $response->json('choices.0.message');

        if (! is_array($message)) {
            throw AiProviderException::requestFailed('پاسخ نامعتبر از سرویس هوش مصنوعی دریافت شد.');
        }

        return $message;
    }

    private function isTransientFailure(\Throwable $exception): bool
    {
        return $exception instanceof ConnectionException
            || ($exception instanceof RequestException && in_array($exception->response->status(), [408, 429, 500, 502, 503, 504], true));
    }

    /**
     * @return array<int, string> model ids offered by this provider, for
     *                            the "انتخاب مدل" dropdown in settings.
     */
    public function listModels(): array
    {
        $response = Http::withToken($this->api_key)
            ->baseUrl(rtrim($this->base_url, '/'))
            ->timeout(30)
            ->get('/models');

        if ($response->failed()) {
            throw AiProviderException::requestFailed(
                $response->json('error.message') ?? "HTTP {$response->status()}",
            );
        }

        return collect($response->json('data', []))
            ->pluck('id')
            ->filter()
            ->sort()
            ->values()
            ->all();
    }
}
