<?php

namespace App\Filament\Pages;

use App\Domain\Crm\GuestMessenger;
use App\Enums\MessageChannel;
use App\Enums\MessageDeliveryStatus;
use App\Filament\Pages\Concerns\HasAccessControlledPage;
use App\Models\CustomerMessage;
use App\Models\Guest;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;

/**
 * One place to see every customer conversation (email, SMS, WhatsApp),
 * most recently active first, and answer from it by email or SMS. Reads
 * the same customer_messages log as each guest's «مکالمات» tab. Incoming
 * mail isn't fetched (that would need a mailbox connection) - what shows
 * as received is WhatsApp.
 */
class CommunicationInbox extends Page
{
    use HasAccessControlledPage;

    protected string $view = 'filament.pages.communication-inbox';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInbox;

    protected static string|\UnitEnum|null $navigationGroup = 'CRM و مشتریان';

    protected static ?string $navigationLabel = 'صندوق پیام';

    protected static ?string $title = 'صندوق پیام';

    #[Url(as: 'guest')]
    public ?int $guestId = null;

    public string $channel = 'email';

    public string $subject = '';

    public string $body = '';

    public static function accessControlPermission(): string
    {
        return 'communication-inbox.access';
    }

    /**
     * The latest message of each of the 50 most recently active guests.
     *
     * @return Collection<int, CustomerMessage>
     */
    public function conversations(): Collection
    {
        $lastIds = CustomerMessage::query()
            ->select('guest_id', DB::raw('max(id) as last_id'))
            ->groupBy('guest_id')
            ->orderByDesc('last_id')
            ->limit(50)
            ->pluck('last_id');

        return CustomerMessage::query()
            ->with('guest')
            ->whereIn('id', $lastIds)
            ->orderByDesc('id')
            ->get();
    }

    public function selectedGuest(): ?Guest
    {
        return $this->guestId ? Guest::find($this->guestId) : null;
    }

    /**
     * The selected guest's last 100 messages, oldest first.
     *
     * @return Collection<int, CustomerMessage>
     */
    public function thread(): Collection
    {
        if (! $this->guestId) {
            return collect();
        }

        return CustomerMessage::query()
            ->with('sender')
            ->where('guest_id', $this->guestId)
            ->latest('id')
            ->limit(100)
            ->get()
            ->reverse()
            ->values();
    }

    public function selectGuest(int $guestId): void
    {
        $this->guestId = $guestId;
        $this->reset('subject', 'body');
    }

    public function send(): void
    {
        $guest = $this->selectedGuest();

        if (! $guest) {
            return;
        }

        $channel = MessageChannel::tryFrom($this->channel);

        $this->validate([
            'channel' => ['required', 'in:email,sms'],
            'subject' => [$channel === MessageChannel::Email ? 'required' : 'nullable', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:'.($channel === MessageChannel::Email ? 5000 : 500)],
        ], [
            'subject.required' => 'موضوع ایمیل را وارد کنید.',
            'body.required' => 'متن پیام را وارد کنید.',
        ]);

        $messenger = app(GuestMessenger::class);

        $message = $channel === MessageChannel::Email
            ? $messenger->sendEmail($guest, $this->subject, $this->body, auth()->user())
            : $messenger->sendSms($guest, $this->body, auth()->user());

        if ($message->status === MessageDeliveryStatus::Sent) {
            Notification::make()->title('پیام ارسال شد.')->success()->send();
            $this->reset('subject', 'body');

            return;
        }

        Notification::make()->title('پیام ارسال نشد.')->body($message->error)->danger()->persistent()->send();
    }
}
