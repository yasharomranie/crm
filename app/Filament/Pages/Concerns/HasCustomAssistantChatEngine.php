<?php

namespace App\Filament\Pages\Concerns;

use App\Domain\Ai\AssistantConsultationService;
use App\Domain\Ai\CrmToolCatalog;
use App\Domain\Ai\DocumentTextExtractor;
use App\Domain\Ai\KnowledgeNoteExtractor;
use App\Domain\Ai\SafeUrlFetcher;
use App\Enums\AiAssistantOcrUploadStatus;
use App\Enums\AiKnowledgeEntrySource;
use App\Enums\AiKnowledgeEntryStatus;
use App\Enums\AiMessageRole;
use App\Exceptions\AiProviderException;
use App\Jobs\OcrKnowledgeUploadJob;
use App\Models\AiAssistantConsultation;
use App\Models\AiAssistantKnowledgeEntry;
use App\Models\AiAssistantOcrUpload;
use App\Models\AiAssistantProfile;
use App\Models\AiAssistantScheduledPrompt;
use App\Models\AiAssistantWatchedSource;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\AiProvider;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\WithFileUploads;

/**
 * A small sibling of HasAiAssistantEngine, for the custom assistants
 * created in the AI Assistants Hub (app/Filament/Pages/
 * AiAssistantsHub.php). It reuses the exact same AiConversation/AiMessage
 * models and AiProvider::chatCompletion() call, but never calls the
 * ~50-tool agent loop that trait runs - a custom profile's only
 * "capability" beyond the system prompt the user wrote for it is the two
 * narrow, self-contained tools below: save_knowledge_entry, which lets it
 * grow its own knowledge base through ordinary use, and fetch_url, which
 * lets it read a specific page the user points it to and turn what it
 * finds into knowledge entries the same way - plus the document-upload
 * path (processKnowledgeUpload) below, a third way to seed the same
 * knowledge base from a file the owner already has, and consult_assistant
 * (AssistantConsultationService), which lets this profile hand a specific
 * question to another visible, consultable profile when it's outside its
 * own specialty, instead of guessing or the user manually forwarding the
 * whole conversation (forwardConversationTo).
 */
trait HasCustomAssistantChatEngine
{
    use WithFileUploads;

    /**
     * Per sendToProfile() call, not per tool call - a profile could
     * otherwise ask several different colleagues in the same round and
     * multiply the cost of a single user message.
     */
    private const MAX_CONSULTATIONS_PER_SEND = 3;

    public ?int $activeProfileId = null;

    public ?int $conversationId = null;

    public string $body = '';

    public function activeProfile(): ?AiAssistantProfile
    {
        if (! $this->activeProfileId) {
            return null;
        }

        return AiAssistantProfile::query()
            ->visibleTo(Auth::user())
            ->find($this->activeProfileId);
    }

    /**
     * The active profile, but only when the current user is its creator -
     * guards every knowledge-management and settings action below the
     * same way deleteProfile() (AiAssistantsHub) already guards deletion.
     */
    private function ownedActiveProfile(): ?AiAssistantProfile
    {
        return AiAssistantProfile::query()
            ->where('created_by', Auth::id())
            ->find($this->activeProfileId);
    }

    /**
     * @var array<string, string> model id => model id - loaded on demand
     *                            (loadModelOptionsForProfile()), not on
     *                            every render.
     */
    public array $modelOptionsForProfile = [];

    public function loadModelOptionsForProfile(): void
    {
        if ($this->modelOptionsForProfile !== []) {
            return;
        }

        $provider = AiProvider::active();

        if (! $provider) {
            return;
        }

        try {
            $models = $provider->listModels();
            $this->modelOptionsForProfile = array_combine($models, $models);
        } catch (\Throwable) {
            $this->modelOptionsForProfile = [];
        }
    }

    /**
     * Only the profile's own creator may change which model it uses -
     * same ownership rule as deleting it (AiAssistantsHub::deleteProfile).
     */
    public function updateProfileModel(?string $model): void
    {
        $this->ownedActiveProfile()?->update(['model' => filled($model) ? $model : null]);
    }

    /**
     * @return Collection<int, AiConversation>
     */
    public function getProfileConversations(): Collection
    {
        if (! $this->activeProfileId) {
            return new Collection;
        }

        return AiConversation::query()
            ->where('user_id', Auth::id())
            ->where('ai_assistant_profile_id', $this->activeProfileId)
            ->latest('updated_at')
            ->get();
    }

    public function getSelectedProfileConversation(): ?AiConversation
    {
        if (! $this->conversationId) {
            return null;
        }

        return AiConversation::query()
            ->where('user_id', Auth::id())
            ->where('ai_assistant_profile_id', $this->activeProfileId)
            ->find($this->conversationId);
    }

    public function getProfileMessages(): Collection
    {
        return $this->getSelectedProfileConversation()?->messages()->get() ?? new Collection;
    }

    public function openProfile(int $profileId): void
    {
        $profile = AiAssistantProfile::query()->visibleTo(Auth::user())->find($profileId);

        if (! $profile) {
            return;
        }

        $this->activeProfileId = $profile->id;
        $this->conversationId = $this->getProfileConversations()->first()?->id;
        $this->body = '';
    }

    public function closeProfile(): void
    {
        $this->activeProfileId = null;
        $this->conversationId = null;
        $this->body = '';
    }

    public function newProfileConversation(): void
    {
        $conversation = AiConversation::create([
            'user_id' => Auth::id(),
            'ai_assistant_profile_id' => $this->activeProfileId,
        ]);

        $this->conversationId = $conversation->id;
        $this->body = '';
    }

    public function selectProfileConversation(int $conversationId): void
    {
        $this->conversationId = $conversationId;
        $this->body = '';
    }

    /**
     * Conversations belong to the signed-in user (not the profile's
     * creator), same as getProfileConversations() - so unlike the
     * knowledge-base and settings actions above, this isn't gated through
     * ownedActiveProfile(): anyone who can see and chat with a profile can
     * manage their own conversation list with it, shared profile or not.
     */
    public function deleteConversation(int $conversationId): void
    {
        $conversation = AiConversation::query()
            ->where('user_id', Auth::id())
            ->where('ai_assistant_profile_id', $this->activeProfileId)
            ->find($conversationId);

        if (! $conversation) {
            return;
        }

        $conversation->delete();

        if ($this->conversationId === $conversationId) {
            $this->conversationId = $this->getProfileConversations()->first()?->id;
        }
    }

    /**
     * Thumbs up/down on one of the profile's own assistant replies, in a
     * conversation the current user owns - clicking the same reaction
     * again clears it, so it's a toggle, not a one-way vote. This is
     * purely a signal for the owner to skim (getProfileMessageFeedbackCounts()
     * below, surfaced on the usage dashboard) - it never feeds back into
     * approvedKnowledgeForPrompt() or anything else automatically.
     */
    public function setMessageFeedback(int $messageId, string $feedback): void
    {
        if (! in_array($feedback, ['up', 'down'], true)) {
            return;
        }

        $message = AiMessage::query()
            ->where('id', $messageId)
            ->where('role', AiMessageRole::Assistant)
            ->whereHas('conversation', fn ($query) => $query
                ->where('user_id', Auth::id())
                ->where('ai_assistant_profile_id', $this->activeProfileId))
            ->first();

        if (! $message) {
            return;
        }

        $message->update(['feedback' => $message->feedback === $feedback ? null : $feedback]);
    }

    public function renameConversation(int $conversationId, string $title): void
    {
        $title = trim($title);

        if ($title === '') {
            return;
        }

        AiConversation::query()
            ->where('user_id', Auth::id())
            ->where('ai_assistant_profile_id', $this->activeProfileId)
            ->whereKey($conversationId)
            ->update(['title' => $title]);
    }

    public function sendToProfile(): void
    {
        $profile = $this->activeProfile();
        $body = trim($this->body);

        if ($body === '' || ! $profile) {
            return;
        }

        $provider = AiProvider::active();

        if (! $provider) {
            Notification::make()->title(AiProviderException::noActiveProvider()->getMessage())->warning()->send();

            return;
        }

        $conversation = $this->getSelectedProfileConversation() ?? AiConversation::create([
            'user_id' => Auth::id(),
            'ai_assistant_profile_id' => $profile->id,
        ]);
        $this->conversationId = $conversation->id;

        $conversation->messages()->create(['role' => AiMessageRole::User, 'content' => $body]);
        $this->body = '';

        if (! $conversation->title) {
            $conversation->update(['title' => $conversation->deriveTitle()]);
        }

        $this->runLoopAndNotify($conversation, $provider, $profile);
    }

    /**
     * Discards the active conversation's last reply and asks the provider
     * again for the same context - lets a weak answer be retried without
     * retyping the question. Only acts when that last message really is a
     * finished assistant reply (never a user message, or a tool message
     * from an agent loop that didn't finish), the same ownership check as
     * setMessageFeedback() above.
     */
    public function regenerateLastResponse(): void
    {
        $profile = $this->activeProfile();
        $conversation = $this->getSelectedProfileConversation();

        if (! $profile || ! $conversation) {
            return;
        }

        $lastMessage = $conversation->messages()->latest('id')->first();

        if (! $lastMessage || $lastMessage->role !== AiMessageRole::Assistant) {
            return;
        }

        $provider = AiProvider::active();

        if (! $provider) {
            Notification::make()->title(AiProviderException::noActiveProvider()->getMessage())->warning()->send();

            return;
        }

        $lastMessage->delete();

        $this->runLoopAndNotify($conversation, $provider, $profile);
    }

    private function runLoopAndNotify(AiConversation $conversation, AiProvider $provider, AiAssistantProfile $profile): void
    {
        try {
            $this->runProfileAgentLoop($conversation, $provider, $profile);
        } catch (AiProviderException $exception) {
            Notification::make()->title($exception->getMessage())->danger()->send();
        }

        $this->dispatch('message-sent');
    }

    /**
     * Mirrors HasAiAssistantEngine::runAgentLoop()'s tool round-trip
     * pattern, but capped at 3 rounds (instead of 5) and offering exactly
     * one tool - this never needs the multi-tool, multi-round agent loop
     * that trait runs.
     */
    private function runProfileAgentLoop(AiConversation $conversation, AiProvider $provider, AiAssistantProfile $profile): void
    {
        $messages = [
            ['role' => 'system', 'content' => $this->buildProfileSystemPrompt($profile)],
            ...$conversation->messages()->get()->map(fn ($message) => $message->toChatPayload())->all(),
        ];

        $consultationsRemaining = self::MAX_CONSULTATIONS_PER_SEND;

        for ($round = 0; $round < 3; $round++) {
            $assistantMessage = $provider->chatCompletion($messages, $this->profileToolDefinitions($profile), $profile->model);
            $toolCalls = $assistantMessage['tool_calls'] ?? null;

            if (! $toolCalls && filled($assistantMessage['content'] ?? null)) {
                $this->streamAssistantContent($assistantMessage['content']);
            }

            $conversation->messages()->create([
                'role' => AiMessageRole::Assistant,
                'content' => $assistantMessage['content'] ?? null,
                'tool_calls' => $toolCalls,
            ]);

            $messages[] = $assistantMessage;

            if (! $toolCalls) {
                return;
            }

            foreach ($toolCalls as $toolCall) {
                $arguments = json_decode($toolCall['function']['arguments'] ?? '{}', true) ?: [];
                $toolName = $toolCall['function']['name'] ?? null;
                $result = match (true) {
                    $toolName === 'fetch_url' => $this->fetchUrlFromTool($profile, $arguments),
                    $toolName === 'consult_assistant' => $this->consultAssistantFromTool($profile, $conversation, $arguments, $consultationsRemaining),
                    is_string($toolName) && array_key_exists($toolName, $this->grantedCrmToolNames($profile)) => app(CrmToolCatalog::class)->execute($toolName, $arguments),
                    default => $this->saveKnowledgeEntryFromTool($profile, $arguments),
                };
                $encodedResult = json_encode($result, JSON_UNESCAPED_UNICODE);

                $conversation->messages()->create([
                    'role' => AiMessageRole::Tool,
                    'content' => $encodedResult,
                    'tool_call_id' => $toolCall['id'],
                    'tool_name' => $toolCall['function']['name'],
                ]);

                $messages[] = [
                    'role' => 'tool',
                    'tool_call_id' => $toolCall['id'],
                    'content' => $encodedResult,
                ];
            }
        }
    }

    /**
     * A progressive, word-by-word reveal of the final answer, streamed to
     * the "assistant-stream" element (ai-assistants-hub.blade.php) via
     * Livewire's wire:stream while this request is still in flight - the
     * message itself is only persisted once this finishes, so a page
     * reload or a second device always just sees the complete answer.
     * This is a client-side typing effect, not a pass-through of the
     * provider's own token stream: AiProvider::chatCompletion() still
     * waits for the full response first. A true token-level relay would
     * need every provider (OpenAI, and the GapGPT-style resellers this
     * app also supports) to reliably support SSE, and would require every
     * existing chatCompletion() fake across the test suite to speak SSE -
     * not worth it for what is, from the user's seat, the same experience.
     */
    private function streamAssistantContent(string $content): void
    {
        if ($content === '' || app()->runningUnitTests()) {
            return;
        }

        foreach (preg_split('/(?<=\s)/u', $content, -1, PREG_SPLIT_NO_EMPTY) as $chunk) {
            // e(): the stream is inserted client-side via insertAdjacentHTML
            // (Livewire's wire:stream), not treated as text - unescaped
            // content could be read as markup instead of displayed as-is.
            $this->stream(content: e($chunk), to: 'assistant-stream');
            usleep(20000);
        }
    }

    private function buildProfileSystemPrompt(AiAssistantProfile $profile): string
    {
        $prompt = $profile->description ?: 'شما یک دستیار هوشمند کمکی هستید.';
        $knowledge = $profile->approvedKnowledgeForPrompt();

        if ($knowledge !== []) {
            $prompt .= "\n\nدانش انباشته‌شدهٔ این دستیار از گفتگوهای قبلی - در پاسخ‌هایت از این نکات استفاده کن:\n- ".implode("\n- ", $knowledge);
        }

        if ($profile->fetch_url_enabled) {
            $prompt .= "\n\nاگر کاربر یک لینک داد یا صریحاً خواست دربارهٔ موضوعی از وب اطلاعات بیاوری، از ابزار fetch_url برای خواندن آن صفحه استفاده کن؛ خودسرانه و بدون درخواست کاربر به هیچ لینکی سر نزن.";
        }

        $crmTools = $this->grantedCrmToolNames($profile);

        if ($crmTools !== []) {
            $prompt .= "\n\nبرای سؤالات دربارهٔ وضعیت واقعی اقامتگاه (مثل اتاق خالی، رزرو، مشتری، تیکت پشتیبانی)، به‌جای حدس زدن یا تکیه بر دانش‌نامهٔ خودت، از این ابزارها استفاده کن تا جواب دقیق و به‌روز بدهی: ".implode('، ', array_keys($crmTools)).'.';
        }

        $consultants = app(AssistantConsultationService::class)->availableConsultantsFor($profile, Auth::user());

        if ($consultants->isNotEmpty()) {
            $directory = $consultants
                ->map(fn (AiAssistantProfile $consultant) => '- شناسه '.$consultant->id.': '.$consultant->displayName().' (تخصص: '.$this->describeSpecialty($consultant).')')
                ->implode("\n");

            $prompt .= "\n\nدستیارهای دیگری که در دسترس‌اند و در صورت نیاز می‌توانی با ابزار consult_assistant یک سؤال مشخص از آن‌ها بپرسی:\n{$directory}\nفقط وقتی سؤال واقعاً از تخصص خودت خارج است از این ابزار استفاده کن، نه برای هر سؤال، و سؤال را کوتاه و مستقل بپرس چون آن دستیار تاریخچهٔ این گفتگو را نمی‌بیند.";
        }

        return $prompt;
    }

    private function describeSpecialty(AiAssistantProfile $profile): string
    {
        if (filled($profile->specialties)) {
            return implode('، ', $profile->specialties);
        }

        return Str::limit((string) $profile->description, 80);
    }

    /**
     * The real-CRM-data tools (CrmToolCatalog) this profile's owner has
     * explicitly granted it (AiAssistantProfile::$allowed_tools), filtered
     * down to tools that still exist in the catalog and to whether the
     * CURRENT acting user (not necessarily the owner - a shared profile
     * can be chatted with by anyone who can see it) actually holds
     * "ai-assistant.report", the same permission that gates these same
     * read-only lookups for the built-in management assistant. Granting a
     * tool on the profile only ever narrows access further - it can never
     * grant a viewer a permission they don't already have.
     *
     * @return array<string, array{description: string, parameters: array<string, mixed>}>
     */
    private function grantedCrmToolNames(AiAssistantProfile $profile): array
    {
        if (! Auth::user()?->can('ai-assistant.report')) {
            return [];
        }

        $catalog = app(CrmToolCatalog::class)->definitions();

        return collect($profile->allowed_tools ?? [])
            ->filter(fn (string $toolName) => isset($catalog[$toolName]))
            ->mapWithKeys(fn (string $toolName) => [$toolName => $catalog[$toolName]])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function profileToolDefinitions(AiAssistantProfile $profile): array
    {
        $tools = [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'save_knowledge_entry',
                    'description' => 'یک نکتهٔ مهم و قابل‌استفاده در گفتگوهای آیندهٔ همین دستیار را در دانش‌نامه‌اش ذخیره می‌کند. فقط برای اطلاعات واقعاً مفید و پایدار استفاده کن (نه چیزهای موقت یا خاص همین یک پیام).',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'content' => [
                                'type' => 'string',
                                'description' => 'متن نکته، کوتاه و دقیق، به فارسی.',
                            ],
                        ],
                        'required' => ['content'],
                    ],
                ],
            ],
        ];

        if ($profile->fetch_url_enabled) {
            $tools[] = [
                'type' => 'function',
                'function' => [
                    'name' => 'fetch_url',
                    'description' => 'محتوای متنی یک صفحهٔ وب عمومی را می‌خواند، فقط وقتی کاربر خودش لینکی داده یا صریحاً خواسته دربارهٔ موضوعی از یک منبع وب اطلاعات بیاوری. بعد از خواندن، نکته‌های مفید را با save_knowledge_entry ذخیره کن.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'url' => [
                                'type' => 'string',
                                'description' => 'آدرس کامل صفحهٔ وب (باید با http:// یا https:// شروع شود).',
                            ],
                        ],
                        'required' => ['url'],
                    ],
                ],
            ];
        }

        $consultants = app(AssistantConsultationService::class)->availableConsultantsFor($profile, Auth::user());

        if ($consultants->isNotEmpty()) {
            $tools[] = [
                'type' => 'function',
                'function' => [
                    'name' => 'consult_assistant',
                    'description' => 'یک سؤال مشخص و مستقل را از دستیار دیگری که در تخصصی دیگر مهارت دارد می‌پرسد و پاسخش را برمی‌گرداند. فقط وقتی سؤال واقعاً از تخصص خودت خارج است از این ابزار استفاده کن.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'target_profile_id' => [
                                'type' => 'integer',
                                'enum' => $consultants->pluck('id')->all(),
                                'description' => 'شناسهٔ دستیاری که باید از او سؤال شود (از فهرست دستور سیستمی).',
                            ],
                            'question' => [
                                'type' => 'string',
                                'description' => 'سؤال مشخص و مستقل، بدون فرض دانستن تاریخچهٔ این گفتگو.',
                            ],
                        ],
                        'required' => ['target_profile_id', 'question'],
                    ],
                ],
            ];
        }

        foreach ($this->grantedCrmToolNames($profile) as $toolName => $definition) {
            $tools[] = [
                'type' => 'function',
                'function' => [
                    'name' => $toolName,
                    'description' => $definition['description'],
                    'parameters' => $definition['parameters'],
                ],
            ];
        }

        return $tools;
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function saveKnowledgeEntryFromTool(AiAssistantProfile $profile, array $arguments): array
    {
        $content = trim((string) ($arguments['content'] ?? ''));

        if ($content === '') {
            return ['error' => 'متن نکته خالی بود، ذخیره نشد.'];
        }

        $entry = $profile->recordAiSuggestedKnowledge($content);

        return [
            'saved' => true,
            'status' => $entry->status->value,
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function fetchUrlFromTool(AiAssistantProfile $profile, array $arguments): array
    {
        if (! $profile->fetch_url_enabled) {
            return ['error' => 'دسترسی به وب برای این دستیار غیرفعال است.'];
        }

        $url = trim((string) ($arguments['url'] ?? ''));

        if ($url === '') {
            return ['error' => 'آدرسی داده نشد.'];
        }

        return app(SafeUrlFetcher::class)->fetch($url);
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function consultAssistantFromTool(AiAssistantProfile $requester, AiConversation $conversation, array $arguments, int &$consultationsRemaining): array
    {
        if ($consultationsRemaining <= 0) {
            return ['error' => 'سقف تعداد مشورت در این پیام به پایان رسیده است.'];
        }

        $targetId = (int) ($arguments['target_profile_id'] ?? 0);
        $question = trim((string) ($arguments['question'] ?? ''));

        if ($targetId <= 0 || $question === '') {
            return ['error' => 'شناسهٔ دستیار یا سؤال نامعتبر است.'];
        }

        $service = app(AssistantConsultationService::class);
        $target = $service->availableConsultantsFor($requester, Auth::user())->firstWhere('id', $targetId);

        if (! $target) {
            return ['error' => 'این دستیار برای مشورت در دسترس نیست.'];
        }

        $consultationsRemaining--;

        return $service->consult($requester, $target, $question, $conversation);
    }

    /**
     * The directory shown in the knowledge panel, so the owner can see
     * which colleagues this profile is allowed to consult before it
     * happens mid-conversation.
     *
     * @return Collection<int, AiAssistantProfile>
     */
    public function getAvailableConsultants(): Collection
    {
        $profile = $this->activeProfile();

        if (! $profile) {
            return new Collection;
        }

        return app(AssistantConsultationService::class)->availableConsultantsFor($profile, Auth::user());
    }

    /**
     * @return Collection<int, AiAssistantKnowledgeEntry>
     */
    public function getKnowledgeEntries(): Collection
    {
        return $this->activeProfile()?->knowledgeEntries()->get() ?? new Collection;
    }

    public function pendingKnowledgeCount(): int
    {
        return $this->ownedActiveProfile()?->knowledgeEntries()->pending()->count() ?? 0;
    }

    /**
     * A manually-typed note is written by the profile's own creator, so
     * unlike an AI-suggested one it needs no review - it's Approved
     * immediately.
     */
    public function addManualKnowledgeEntry(string $content): void
    {
        $profile = $this->ownedActiveProfile();
        $content = trim($content);

        if (! $profile || $content === '') {
            return;
        }

        $profile->knowledgeEntries()->create([
            'content' => $content,
            'source' => AiKnowledgeEntrySource::Manual,
            'status' => AiKnowledgeEntryStatus::Approved,
        ]);
    }

    public function approveKnowledgeEntry(int $entryId): void
    {
        $this->ownedActiveProfile()?->knowledgeEntries()->whereKey($entryId)->update(['status' => AiKnowledgeEntryStatus::Approved]);
    }

    public function updateKnowledgeEntry(int $entryId, string $content): void
    {
        $content = trim($content);

        if ($content === '') {
            return;
        }

        $this->ownedActiveProfile()?->knowledgeEntries()->whereKey($entryId)->update(['content' => $content]);
    }

    public function deleteKnowledgeEntry(int $entryId): void
    {
        $this->ownedActiveProfile()?->knowledgeEntries()->whereKey($entryId)->delete();
    }

    public function toggleKnowledgeAutoSave(): void
    {
        $profile = $this->ownedActiveProfile();
        $profile?->update(['knowledge_auto_save' => ! $profile->knowledge_auto_save]);
    }

    /**
     * $specialties is a comma-separated string straight from the input
     * field - split and trimmed here rather than asking the UI to post an
     * array, matching the plain-text-input pattern used throughout this
     * trait (addManualKnowledgeEntry, addWatchedSource).
     */
    public function updateProfileSpecialties(string $specialties): void
    {
        $tags = collect(explode(',', $specialties))
            ->map(fn (string $tag) => trim($tag))
            ->filter()
            ->values()
            ->all();

        $this->ownedActiveProfile()?->update(['specialties' => $tags]);
    }

    public function toggleProfileConsultable(): void
    {
        $profile = $this->ownedActiveProfile();
        $profile?->update(['consultable' => ! $profile->consultable]);
    }

    public function toggleProfileFetchUrl(): void
    {
        $profile = $this->ownedActiveProfile();
        $profile?->update(['fetch_url_enabled' => ! $profile->fetch_url_enabled]);
    }

    /**
     * Grants or revokes ONE CrmToolCatalog tool on the active profile -
     * only the owner can change this, and only an actual catalog tool
     * name is ever accepted, so a crafted/arbitrary string can never end
     * up inside allowed_tools.
     */
    public function toggleAllowedCrmTool(string $toolName): void
    {
        $profile = $this->ownedActiveProfile();

        if (! $profile || ! app(CrmToolCatalog::class)->label($toolName)) {
            return;
        }

        $allowed = collect($profile->allowed_tools ?? []);

        $profile->update([
            'allowed_tools' => $allowed->contains($toolName)
                ? $allowed->reject(fn (string $name) => $name === $toolName)->values()->all()
                : [...$allowed->all(), $toolName],
        ]);
    }

    /**
     * The knowledge panel's CRM-tool checklist renders only for a viewer
     * who actually holds "ai-assistant.report" (otherwise granting a tool
     * would be pointless - grantedCrmToolNames() would just filter it back
     * out at send time), so the catalog is only exposed here under that
     * same gate.
     *
     * @return array<string, string>
     */
    public function getCrmToolCatalogForOwner(): array
    {
        if (! Auth::user()?->can('ai-assistant.report')) {
            return [];
        }

        $catalog = app(CrmToolCatalog::class);

        return collect($catalog->definitions())
            ->mapWithKeys(fn (array $definition, string $toolName) => [$toolName => $catalog->label($toolName) ?? $toolName])
            ->all();
    }

    /**
     * A short, human-readable description of what a `consult_assistant` or
     * `fetch_url` tool call actually did, shown as a small chip inline in
     * the chat (ai-assistants-hub.blade.php) so the collaboration this
     * profile does behind the scenes isn't entirely invisible to the user
     * - unlike save_knowledge_entry, which stays silent since it's routine
     * bookkeeping rather than something the user would want surfaced every
     * time. Returns null for anything that shouldn't get a chip (an
     * unrecognized tool, or a call that errored out).
     */
    public function describeToolActivity(AiMessage $message): ?string
    {
        if ($message->role !== AiMessageRole::Tool) {
            return null;
        }

        $result = json_decode((string) $message->content, true);
        $result = is_array($result) ? $result : [];

        return match ($message->tool_name) {
            'consult_assistant' => isset($result['consulted'])
                ? 'از «'.$result['consulted'].'» مشورت گرفت'
                : null,
            'fetch_url' => isset($result['url'])
                ? 'صفحهٔ «'.(parse_url((string) $result['url'], PHP_URL_HOST) ?: $result['url']).'» را خواند'
                : null,
            default => null,
        };
    }

    /**
     * Recent consultations this profile took part in, either side -
     * AiAssistantConsultation is written on every consult_assistant call
     * (AssistantConsultationService::consult) but otherwise has no UI of
     * its own, so without this the exchange only shows up as the chat
     * chip above for the asking side, and not at all for the answering
     * side.
     *
     * @return Collection<int, AiAssistantConsultation>
     */
    public function getConsultationHistory(): Collection
    {
        $profile = $this->ownedActiveProfile();

        if (! $profile) {
            return new Collection;
        }

        return AiAssistantConsultation::query()
            ->where('consulting_ai_assistant_profile_id', $profile->id)
            ->orWhere('consulted_ai_assistant_profile_id', $profile->id)
            ->with(['consultingProfile', 'consultedProfile'])
            ->latest()
            ->limit(10)
            ->get();
    }

    /**
     * @return Collection<int, AiAssistantWatchedSource>
     */
    public function getWatchedSources(): Collection
    {
        return $this->activeProfile()?->watchedSources()->get() ?? new Collection;
    }

    public function addWatchedSource(string $url, string $label = ''): void
    {
        $profile = $this->ownedActiveProfile();
        $url = trim($url);

        if (! $profile || $url === '') {
            return;
        }

        $profile->watchedSources()->create([
            'url' => $url,
            'label' => trim($label) !== '' ? trim($label) : null,
        ]);
    }

    public function deleteWatchedSource(int $watchedSourceId): void
    {
        $this->ownedActiveProfile()?->watchedSources()->whereKey($watchedSourceId)->delete();
    }

    /**
     * @return Collection<int, AiAssistantScheduledPrompt>
     */
    public function getScheduledPrompts(): Collection
    {
        return $this->activeProfile()?->scheduledPrompts()->get() ?? new Collection;
    }

    /**
     * $runAtTime is a plain "HH:MM" string straight from an <input
     * type="time">, matching the free-text-input pattern used throughout
     * this trait (addWatchedSource, updateProfileSpecialties).
     */
    public function addScheduledPrompt(string $prompt, string $runAtTime): void
    {
        $profile = $this->ownedActiveProfile();
        $prompt = trim($prompt);

        if (! $profile || $prompt === '' || ! preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $runAtTime)) {
            return;
        }

        $profile->scheduledPrompts()->create([
            'prompt' => $prompt,
            'run_at_time' => $runAtTime,
        ]);
    }

    public function deleteScheduledPrompt(int $scheduledPromptId): void
    {
        $this->ownedActiveProfile()?->scheduledPrompts()->whereKey($scheduledPromptId)->delete();
    }

    public function toggleScheduledPromptActive(int $scheduledPromptId): void
    {
        $scheduledPrompt = $this->ownedActiveProfile()?->scheduledPrompts()->whereKey($scheduledPromptId)->first();
        $scheduledPrompt?->update(['is_active' => ! $scheduledPrompt->is_active]);
    }

    public ?UploadedFile $knowledgeUploadFile = null;

    /**
     * Third way to build a profile's knowledge base, alongside ordinary
     * chat and the fetch_url tool: the owner uploads a file they already
     * have (a price list, a law's text, a catalogue), its text is pulled
     * out locally (DocumentTextExtractor - no AI call, no cost), and then
     * KnowledgeNoteExtractor turns that raw text into a handful of short,
     * stand-alone notes via a bounded, owner-triggered handful of
     * chat-completion calls - unlike WatchedSourceChecker's unattended
     * daily runs, which never call the AI at all. Notes land in the same
     * review queue as everything else.
     *
     * A PDF DocumentTextExtractor finds no text in at all is usually a
     * scan (a photographed/scanned document with no text layer, as
     * opposed to a digitally-generated PDF) rather than an empty file -
     * that case is handed off to OcrKnowledgeUploadJob instead of simply
     * reported as an error, since OCR takes minutes, not seconds, and has
     * no place inline in this request.
     */
    public function processKnowledgeUpload(): void
    {
        $profile = $this->ownedActiveProfile();

        if (! $profile || ! $this->knowledgeUploadFile) {
            return;
        }

        $this->validate(['knowledgeUploadFile' => ['required', 'file', 'mimes:txt,docx,pdf', 'max:10240']]);

        $provider = AiProvider::active();

        if (! $provider) {
            Notification::make()->title(AiProviderException::noActiveProvider()->getMessage())->warning()->send();

            return;
        }

        $extension = $this->knowledgeUploadFile->getClientOriginalExtension();

        $extraction = app(DocumentTextExtractor::class)->extract(
            $this->knowledgeUploadFile->getRealPath(),
            $extension,
        );

        if (isset($extraction['error'])) {
            if (strtolower($extension) === 'pdf') {
                $this->dispatchOcrKnowledgeUpload($profile, $provider);

                return;
            }

            Notification::make()->title($extraction['error'])->danger()->send();
            $this->reset('knowledgeUploadFile');

            return;
        }

        $result = app(KnowledgeNoteExtractor::class)->extract($profile, $provider, $extraction['content']);

        foreach ($result['notes'] as $note) {
            $profile->recordAiSuggestedKnowledge($note);
        }

        $this->reset('knowledgeUploadFile');

        if ($result['error'] !== null) {
            Notification::make()->title($result['error'])->danger()->send();

            return;
        }

        Notification::make()->title($result['notes'] === [] ? 'هیچ نکتهٔ قابل‌استفاده‌ای در فایل پیدا نشد.' : count($result['notes']).' نکته از فایل استخراج شد و در صف بررسی قرار گرفت.')->success()->send();
    }

    /**
     * Stores the upload to a durable, private path (the Livewire temp
     * copy is gone once this request ends, but OcrKnowledgeUploadJob runs
     * minutes later on a queue worker) and hands it off to the job -
     * OCR'ing even a handful of pages takes seconds each, so it has no
     * place blocking this web request. The AiAssistantOcrUpload row
     * created here is what getActiveOcrUploadsForActiveProfile() reads
     * to show the owner this is still running, rather than leaving the
     * upload section looking idle for several minutes.
     */
    private function dispatchOcrKnowledgeUpload(AiAssistantProfile $profile, AiProvider $provider): void
    {
        $path = $this->knowledgeUploadFile->store('knowledge-ocr-uploads');

        $ocrUpload = $profile->ocrUploads()->create([
            'original_filename' => $this->knowledgeUploadFile->getClientOriginalName(),
            'status' => AiAssistantOcrUploadStatus::Pending,
        ]);

        OcrKnowledgeUploadJob::dispatch($ocrUpload->id, $provider->id, $path, Auth::id());

        $this->reset('knowledgeUploadFile');

        Notification::make()
            ->title('این فایل احتمالاً یک اسکن است (بدون لایهٔ متنی) - در پس‌زمینه با OCR خوانده می‌شود، چند دقیقه طول می‌کشد.')
            ->body('وضعیت را همین پایین، زیر فرم آپلود، می‌توانید ببینید.')
            ->success()
            ->send();
    }

    /**
     * @return Collection<int, AiAssistantOcrUpload>
     */
    public function getOcrUploadsForActiveProfile(): Collection
    {
        return $this->activeProfile()?->ocrUploads()->take(5)->get() ?? new Collection;
    }

    /**
     * Copies the active conversation's visible messages onto a brand-new
     * conversation under a different profile the user can see - the
     * "manual forward" pattern (never automatic cross-assistant
     * delegation): the target assistant starts a fresh thread that
     * already contains this context, and the user decides what (if
     * anything) to ask it next.
     */
    public function forwardConversationTo(int $targetProfileId): void
    {
        $source = $this->getSelectedProfileConversation();
        $target = AiAssistantProfile::query()->visibleTo(Auth::user())->find($targetProfileId);

        if (! $source || ! $target) {
            return;
        }

        $forwarded = AiConversation::create([
            'user_id' => Auth::id(),
            'ai_assistant_profile_id' => $target->id,
            'title' => 'فوروارد شده از «'.($this->activeProfile()?->displayName() ?? 'دستیار دیگر').'»',
        ]);

        foreach ($source->messages()->get() as $message) {
            if (! in_array($message->role, [AiMessageRole::User, AiMessageRole::Assistant], true) || blank($message->content)) {
                continue;
            }

            $forwarded->messages()->create([
                'role' => $message->role,
                'content' => $message->content,
            ]);
        }

        $this->activeProfileId = $target->id;
        $this->conversationId = $forwarded->id;
        $this->body = '';

        Notification::make()->title('گفتگو برای «'.$target->displayName().'» فوروارد شد.')->success()->send();
    }
}
