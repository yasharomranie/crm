<?php

namespace App\Models;

use App\Exceptions\AiProviderException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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
     * @return array<string, mixed> the assistant message (content and/or tool_calls)
     */
    public function chatCompletion(array $messages, array $tools = []): array
    {
        $payload = [
            'model' => $this->default_model,
            'messages' => $messages,
        ];

        if (filled($tools)) {
            $payload['tools'] = $tools;
            $payload['tool_choice'] = 'auto';
        }

        $response = Http::withToken($this->api_key)
            ->baseUrl(rtrim($this->base_url, '/'))
            ->timeout(90)
            ->post('/chat/completions', $payload);

        if ($response->failed()) {
            throw AiProviderException::requestFailed(
                $response->json('error.message') ?? "HTTP {$response->status()}",
            );
        }

        $message = $response->json('choices.0.message');

        if (! is_array($message)) {
            throw AiProviderException::requestFailed('پاسخ نامعتبر از سرویس هوش مصنوعی دریافت شد.');
        }

        return $message;
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
