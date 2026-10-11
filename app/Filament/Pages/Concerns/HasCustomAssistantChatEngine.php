<?php

namespace App\Filament\Pages\Concerns;

use App\Domain\Ai\AssistantConsultationService;
use App\Domain\Ai\DocumentTextExtractor;
use App\Domain\Ai\SafeUrlFetcher;
use App\Enums\AiKnowledgeEntrySource;
use App\Enums\AiKnowledgeEntryStatus;
use App\Enums\AiMessageRole;
use App\Exceptions\AiProviderException;
use App\Models\AiAssistantConsultation;
use App\Models\AiAssistantKnowledgeEntry;
use App\Models\AiAssistantProfile;
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

    public bool $isThinking = false;

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

        $this->isThinking = true;

        try {
            $this->runProfileAgentLoop($conversation, $provider, $profile);
        } catch (AiProviderException $exception) {
            Notification::make()->title($exception->getMessage())->danger()->send();
        } finally {
            $this->isThinking = false;
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
                $result = match ($toolCall['function']['name'] ?? null) {
                    'fetch_url' => $this->fetchUrlFromTool($profile, $arguments),
                    'consult_assistant' => $this->consultAssistantFromTool($profile, $conversation, $arguments, $consultationsRemaining),
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

    public ?UploadedFile $knowledgeUploadFile = null;

    /**
     * Third way to build a profile's knowledge base, alongside ordinary
     * chat and the fetch_url tool: the owner uploads a file they already
     * have (a price list, a law's text, a catalogue), its text is pulled
     * out locally (DocumentTextExtractor - no AI call, no cost), and then
     * ONE chat-completion call turns that raw text into a handful of
     * short, stand-alone notes - a bounded, owner-triggered cost, unlike
     * WatchedSourceChecker's unattended daily runs, which never call the
     * AI at all. Notes land in the same review queue as everything else.
     */
    public function processKnowledgeUpload(): void
    {
        $profile = $this->ownedActiveProfile();

        if (! $profile || ! $this->knowledgeUploadFile) {
            return;
        }

        $this->validate(['knowledgeUploadFile' => ['required', 'file', 'mimes:txt,docx,pdf', 'max:5120']]);

        $provider = AiProvider::active();

        if (! $provider) {
            Notification::make()->title(AiProviderException::noActiveProvider()->getMessage())->warning()->send();

            return;
        }

        $extraction = app(DocumentTextExtractor::class)->extract(
            $this->knowledgeUploadFile->getRealPath(),
            $this->knowledgeUploadFile->getClientOriginalExtension(),
        );

        if (isset($extraction['error'])) {
            Notification::make()->title($extraction['error'])->danger()->send();
            $this->reset('knowledgeUploadFile');

            return;
        }

        $notes = $this->extractKnowledgeNotesFromDocument($profile, $provider, $extraction['content']);

        foreach ($notes as $note) {
            $profile->recordAiSuggestedKnowledge($note);
        }

        $this->reset('knowledgeUploadFile');

        Notification::make()->title($notes === [] ? 'هیچ نکتهٔ قابل‌استفاده‌ای در فایل پیدا نشد.' : count($notes).' نکته از فایل استخراج شد و در صف بررسی قرار گرفت.')->success()->send();
    }

    /**
     * @return list<string>
     */
    private function extractKnowledgeNotesFromDocument(AiAssistantProfile $profile, AiProvider $provider, string $text): array
    {
        $messages = [
            [
                'role' => 'system',
                'content' => 'تو یک دستیار استخراج اطلاعاتی. از متن کاربر، مهم‌ترین نکات مستقل و قابل‌استفاده را استخراج کن (حداکثر ۱۵ نکته، هر نکته کوتاه و به فارسی). پاسخ تو باید فقط و فقط یک آرایهٔ JSON از رشته‌ها باشد، بدون هیچ توضیح یا متن اضافه.',
            ],
            ['role' => 'user', 'content' => $text],
        ];

        try {
            $response = $provider->chatCompletion($messages, [], $profile->model);
        } catch (AiProviderException $exception) {
            Notification::make()->title($exception->getMessage())->danger()->send();

            return [];
        }

        $notes = json_decode($this->stripJsonFences((string) ($response['content'] ?? '')), true);

        if (! is_array($notes)) {
            return [];
        }

        return collect($notes)
            ->map(fn ($note) => trim((string) $note))
            ->filter(fn (string $note) => $note !== '')
            ->take(15)
            ->values()
            ->all();
    }

    /**
     * Some providers wrap a JSON response in a ```json ... ``` fence even
     * when told not to - strip it before decoding.
     */
    private function stripJsonFences(string $content): string
    {
        $content = trim($content);
        $content = preg_replace('/^```(?:json)?/i', '', $content) ?? $content;
        $content = preg_replace('/```$/', '', $content) ?? $content;

        return trim($content);
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
