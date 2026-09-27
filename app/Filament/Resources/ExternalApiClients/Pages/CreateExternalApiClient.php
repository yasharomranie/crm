<?php

namespace App\Filament\Resources\ExternalApiClients\Pages;

use App\Filament\Resources\ExternalApiClients\ExternalApiClientResource;
use App\Models\ExternalApiClient;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

/**
 * Goes through ExternalApiClient::issue() rather than a plain
 * Model::create() - that's the only place the plain secret is generated,
 * and it must never be persisted or shown again after this one request
 * (see the model's own doc comment). The secret is handed to the admin
 * through a persistent notification, since redirecting to the edit page
 * is the only place left to show it once created() has returned.
 */
class CreateExternalApiClient extends CreateRecord
{
    protected static string $resource = ExternalApiClientResource::class;

    private string $plainSecret;

    protected function handleRecordCreation(array $data): Model
    {
        [$client, $plainSecret] = ExternalApiClient::issue(
            clientName: $data['client_name'],
            allowedOperations: $data['allowed_operations'] ?? [],
            company: $data['company'] ?? null,
            rateLimitPerMinute: $data['rate_limit_per_minute'] ?? null,
            createdBy: $data['created_by'] ?? null,
        );

        if (! empty($data['terminal_id'])) {
            $client->update(['terminal_id' => $data['terminal_id']]);
        }

        if (array_key_exists('is_active', $data) && ! $data['is_active']) {
            $client->update(['is_active' => false]);
        }

        $this->plainSecret = $plainSecret;

        return $client;
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('اکانت API ساخته شد')
            ->body("رمز این اکانت فقط همین یک‌بار نمایش داده می‌شود - همین حالا کپی و برای سامانه‌ی بیرونی ارسال کنید:\n\n{$this->plainSecret}")
            ->persistent();
    }
}
