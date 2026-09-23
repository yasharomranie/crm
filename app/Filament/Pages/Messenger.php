<?php

namespace App\Filament\Pages;

use App\Enums\ConversationType;
use App\Filament\Pages\Concerns\HasAccessControlledPage;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Notifications\NewMessageReceived;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Number;
use Livewire\WithFileUploads;

class Messenger extends Page
{
    use HasAccessControlledPage, WithFileUploads;

    protected string $view = 'filament.pages.messenger';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static string|\UnitEnum|null $navigationGroup = 'ارتباطات داخلی';

    protected static ?string $navigationLabel = 'پیام‌رسان';

    protected static ?string $title = 'پیام‌رسان داخلی';

    public static function accessControlPermission(): string
    {
        return 'messenger.access';
    }

    public ?int $conversationId = null;

    public string $body = '';

    public ?UploadedFile $attachment = null;

    public string $search = '';

    public bool $showNewConversationModal = false;

    public string $newConversationType = 'direct';

    public ?int $newConversationUserId = null;

    public string $newConversationName = '';

    /** @var array<int> */
    public array $newConversationParticipantIds = [];

    public function mount(): void
    {
        $this->conversationId = $this->getConversations()->first()?->id;
    }

    /**
     * Called every few seconds via wire:poll - this is what gives the page
     * its "live" feel without a WebSocket server (Laravel Reverb would be
     * the natural upgrade for true push updates; polling is the pragmatic
     * choice here since it needs no extra long-running process). It also
     * keeps read receipts current: if the user is sitting in a
     * conversation, incoming messages are marked read within one tick.
     */
    public function tick(): void
    {
        $this->getSelectedConversation()?->markReadFor(Auth::user());
    }

    /**
     * @return Collection<int, Conversation>
     */
    public function getConversations(): Collection
    {
        $user = Auth::user();

        return Conversation::query()
            ->forUser($user)
            ->with(['participants.user', 'latestMessage.sender'])
            ->get()
            ->when(
                filled($this->search),
                fn (Collection $conversations) => $conversations->filter(
                    fn (Conversation $conversation) => str_contains(
                        mb_strtolower($conversation->displayNameFor($user)),
                        mb_strtolower($this->search),
                    ),
                ),
            )
            ->values();
    }

    public function getSelectedConversation(): ?Conversation
    {
        if (! $this->conversationId) {
            return null;
        }

        return Conversation::query()
            ->with(['participants.user'])
            ->find($this->conversationId);
    }

    /**
     * Named getConversationMessages(), not getMessages() - Livewire's own
     * HandlesValidation trait reserves a protected getMessages() for
     * custom validation messages, and a class method silently overrides a
     * trait's, so a same-named public method here breaks $this->validate()
     * the moment it's ever called (confirmed live: it threw a
     * type-mismatch error on the first validate() call this page ever
     * made, for the file-attachment feature below).
     *
     * @return Collection<int, Message>
     */
    public function getConversationMessages(): Collection
    {
        $conversation = $this->getSelectedConversation();

        if (! $conversation) {
            return new Collection;
        }

        return $conversation->messages()->with('sender')->get();
    }

    /**
     * @return Collection<int, User>
     */
    public function getSelectableUsers(): Collection
    {
        return User::query()
            ->where('id', '!=', Auth::id())
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    public function selectConversation(int $conversationId): void
    {
        $this->conversationId = $conversationId;
        $this->body = '';
        $this->attachment = null;
        $this->getSelectedConversation()?->markReadFor(Auth::user());
    }

    /**
     * A message can be text, an attachment, or both - only reject it if
     * there's truly nothing to send.
     */
    public function sendMessage(): void
    {
        $body = trim($this->body);

        if ($body === '' && ! $this->attachment) {
            return;
        }

        $this->validate(['attachment' => ['nullable', 'file', 'max:10240']]);

        $conversation = $this->getSelectedConversation();

        if (! $conversation) {
            return;
        }

        $data = [
            'sender_id' => Auth::id(),
            'body' => $body !== '' ? $body : null,
        ];

        if ($this->attachment) {
            $data = [...$data, ...$this->storeAttachment($this->attachment)];
        }

        $message = $conversation->messages()->create($data);

        $conversation->markReadFor(Auth::user());

        $conversation->participants()
            ->where('user_id', '!=', Auth::id())
            ->with('user')
            ->get()
            ->pluck('user')
            ->filter()
            ->each(fn (User $participant) => $participant->notify(new NewMessageReceived($message)));

        $this->body = '';
        $this->attachment = null;

        $this->dispatch('message-sent');
    }

    public function removeAttachment(): void
    {
        $this->attachment = null;
    }

    /**
     * @return array{file_path: string, file_name: string, mime_type: string, size_kb: int}
     */
    private function storeAttachment(UploadedFile $file): array
    {
        return [
            'file_path' => $file->store('message-attachments', config('filesystems.default')),
            'file_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size_kb' => (int) round($file->getSize() / 1024),
        ];
    }

    public function openNewConversationModal(): void
    {
        $this->reset(['newConversationType', 'newConversationUserId', 'newConversationName', 'newConversationParticipantIds']);
        $this->showNewConversationModal = true;
    }

    public function closeNewConversationModal(): void
    {
        $this->showNewConversationModal = false;
    }

    public function startConversation(): void
    {
        if ($this->newConversationType === ConversationType::Group->value) {
            $this->createGroupConversation();

            return;
        }

        if (! $this->newConversationUserId) {
            Notification::make()->title('یک همکار را انتخاب کنید.')->warning()->send();

            return;
        }

        $other = User::find($this->newConversationUserId);

        if (! $other) {
            return;
        }

        $conversation = Conversation::findOrCreateDirect(Auth::user(), $other);

        $this->conversationId = $conversation->id;
        $this->closeNewConversationModal();
    }

    private function createGroupConversation(): void
    {
        $name = trim($this->newConversationName);

        if ($name === '') {
            Notification::make()->title('نام گروه را وارد کنید.')->warning()->send();

            return;
        }

        if (empty($this->newConversationParticipantIds)) {
            Notification::make()->title('حداقل یک عضو برای گروه انتخاب کنید.')->warning()->send();

            return;
        }

        $conversation = Conversation::createGroup($name, Auth::user(), $this->newConversationParticipantIds);

        $this->conversationId = $conversation->id;
        $this->closeNewConversationModal();
    }

    public static function getNavigationBadge(): ?string
    {
        $user = Auth::user();

        if (! $user) {
            return null;
        }

        $count = Message::totalUnreadFor($user);

        return $count > 0 ? Number::toPersian($count) : null;
    }
}
