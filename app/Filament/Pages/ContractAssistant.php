<?php

namespace App\Filament\Pages;

use App\Exceptions\AiProviderException;
use App\Filament\Pages\Concerns\HasAccessControlledPage;
use App\Models\AiProvider;
use App\Models\Contract;
use App\Models\ContractCategory;
use App\Models\ContractCompany;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

class ContractAssistant extends Page
{
    use HasAccessControlledPage;

    protected string $view = 'filament.pages.contract-assistant';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static string|\UnitEnum|null $navigationGroup = 'حقوقی و قرارداد';

    protected static ?string $navigationLabel = 'دستیار حقوقی و قرارداد';

    protected static ?string $title = 'دستیار حقوقی و قرارداد';

    public static function accessControlPermission(): string
    {
        return 'contract-assistant.access';
    }

    public ?int $categoryId = null;

    public ?int $companyId = null;

    public string $counterpartyName = '';

    public string $brief = '';

    public string $contractTitle = '';

    public string $body = '';

    public bool $isGenerating = false;

    public ?int $savedContractId = null;

    /**
     * @var array<int, array{key: string, label: string, message: string}>
     */
    public array $auditWarnings = [];

    /**
     * @return Collection<int, ContractCategory>
     */
    public function getCategories(): Collection
    {
        return ContractCategory::query()->orderBy('name')->get();
    }

    /**
     * @return Collection<int, ContractCompany>
     */
    public function getCompanies(): Collection
    {
        return ContractCompany::query()->orderBy('name')->get();
    }

    public function quickPickCategory(int $categoryId): void
    {
        $this->categoryId = $categoryId;
    }

    /**
     * Drafts a brand-new contract from scratch, then runs the deterministic
     * clause audit against it.
     */
    public function generateDraft(): void
    {
        $brief = trim($this->brief);
        $counterparty = trim($this->counterpartyName);

        if ($brief === '' || $counterparty === '') {
            Notification::make()
                ->title('نام طرف مقابل و توضیح مختصر قرارداد را وارد کنید.')
                ->warning()
                ->send();

            return;
        }

        $provider = AiProvider::active();

        if (! $provider) {
            Notification::make()->title(AiProviderException::noActiveProvider()->getMessage())->warning()->send();

            return;
        }

        $category = $this->categoryId ? ContractCategory::find($this->categoryId) : null;
        $company = $this->companyId ? ContractCompany::find($this->companyId) : null;

        $this->isGenerating = true;

        try {
            $this->body = $this->requestDraft($provider, $category, $company, $counterparty, $brief);
            $this->contractTitle = $this->contractTitle !== '' ? $this->contractTitle : $this->deriveTitle($category, $counterparty);
            // A freshly (re)generated draft is not yet saved under this text.
            $this->savedContractId = null;
            $this->runAudit();
        } catch (AiProviderException $exception) {
            Notification::make()->title($exception->getMessage())->danger()->send();
        } finally {
            $this->isGenerating = false;
        }
    }

    /**
     * Asks the AI to rewrite the current draft, inserting the clauses the
     * audit flagged as missing, then re-runs the audit.
     */
    public function insertSuggestedClauses(): void
    {
        if (trim($this->body) === '' || $this->auditWarnings === []) {
            return;
        }

        $provider = AiProvider::active();

        if (! $provider) {
            Notification::make()->title(AiProviderException::noActiveProvider()->getMessage())->warning()->send();

            return;
        }

        $this->isGenerating = true;

        try {
            $missingLabels = collect($this->auditWarnings)->pluck('label')->implode('، ');

            $message = "متن قرارداد زیر فاقد یا دارای اشارهٔ ناقص به این بندهاست: {$missingLabels}.\n".
                "بندهای مربوطه را در جای مناسب (با شمارهٔ مادهٔ بعدی) به متن اضافه کن و کل متن نهاییِ اصلاح‌شدهٔ قرارداد را کامل برگردان (بدون توضیح اضافه، فقط متن قرارداد).\n\n".
                "متن فعلی قرارداد:\n\n{$this->body}";

            $response = $provider->chatCompletion([
                ['role' => 'system', 'content' => $this->systemPrompt()],
                ['role' => 'user', 'content' => $message],
            ]);

            $content = trim($response['content'] ?? '');

            if ($content !== '') {
                $this->body = $content;
                $this->runAudit();
            }

            Notification::make()->title('بندهای پیشنهادی درج شد.')->success()->send();
        } catch (AiProviderException $exception) {
            Notification::make()->title($exception->getMessage())->danger()->send();
        } finally {
            $this->isGenerating = false;
        }
    }

    /**
     * Archives (or updates) the current draft as a Contract record.
     */
    public function save(): void
    {
        if (trim($this->body) === '') {
            Notification::make()->title('ابتدا یک پیش‌نویس تولید کنید.')->warning()->send();

            return;
        }

        $data = [
            'contract_category_id' => $this->categoryId,
            'contract_company_id' => $this->companyId,
            'counterparty_name' => $this->counterpartyName !== '' ? $this->counterpartyName : 'نامشخص',
            'title' => $this->contractTitle !== '' ? $this->contractTitle : 'قرارداد بدون عنوان',
            'brief' => $this->brief,
            'body' => $this->body,
            'audit_warnings' => $this->auditWarnings,
            'created_by' => Auth::id(),
        ];

        if ($this->savedContractId) {
            Contract::whereKey($this->savedContractId)->update($data);
        } else {
            $this->savedContractId = Contract::create($data)->id;
        }

        Notification::make()->title('قرارداد در آرشیو ذخیره شد.')->success()->send();
    }

    /**
     * Clears the working form for a brand-new contract.
     */
    public function newDraft(): void
    {
        $this->reset([
            'categoryId', 'companyId', 'counterpartyName', 'brief',
            'contractTitle', 'body', 'auditWarnings', 'savedContractId',
        ]);
    }

    public function printUrl(): ?string
    {
        return $this->savedContractId ? route('contracts.print', $this->savedContractId) : null;
    }

    /**
     * Livewire lifecycle hook: fires when the "ویرایش مستقیم متن" textarea
     * is blurred, so a manual edit re-runs the same audit as a fresh draft.
     */
    public function updatedBody(): void
    {
        $this->runAudit();
    }

    private function runAudit(): void
    {
        $this->auditWarnings = Contract::auditText($this->body);
    }

    private function requestDraft(
        AiProvider $provider,
        ?ContractCategory $category,
        ?ContractCompany $company,
        string $counterparty,
        string $brief,
    ): string {
        $context = collect([
            $category ? "نوع قرارداد: {$category->name}" : null,
            $category?->ai_guidance ? "نکات ویژهٔ این نوع قرارداد: {$category->ai_guidance}" : null,
            $company ? "مشخصات کارفرما:\n{$company->identitySummary()}" : null,
            "نام طرف مقابل (پیمانکار/مشاور/مستأجر و غیره): {$counterparty}",
            "توضیح کارفرما دربارهٔ این قرارداد:\n{$brief}",
        ])->filter()->implode("\n\n");

        $response = $provider->chatCompletion([
            ['role' => 'system', 'content' => $this->systemPrompt()],
            ['role' => 'user', 'content' => $context],
        ]);

        $content = trim($response['content'] ?? '');

        if ($content === '') {
            throw AiProviderException::requestFailed('پاسخ خالی از سرویس هوش مصنوعی دریافت شد.');
        }

        return $content;
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
            شما دستیار حقوقی یک مجموعهٔ گردشگری و اقامتی هستید و وظیفهٔ شما نوشتن پیش‌نویس قراردادهای روتین این مجموعه است.

            قوانین:
            - فقط و فقط متن قرارداد را به فارسی رسمی و حقوقی بنویس؛ هیچ مقدمه یا توضیح اضافه‌ای (مثل «البته باید توجه داشت») قبل یا بعد از متن قرارداد نیاور.
            - از قالب Markdown استفاده کن: عنوان هر ماده را با ## بیاور (مثلاً "## ماده ۱ - طرفین قرارداد")، و پاراگراف‌های زیر هر ماده را به‌صورت متن عادی بنویس.
            - ساختار استاندارد را رعایت کن: یک مقدمهٔ کوتاه (تاریخ و طرفین)، سپس به‌ترتیب: طرفین قرارداد، موضوع قرارداد، مدت قرارداد، مبلغ و نحوهٔ پرداخت، تعهدات طرفین، وجه التزام (خسارت تأخیر)، بیمه و مسئولیت، کسورات قانونی، فسخ قرارداد، حل اختلاف و مرجع رسیدگی، و سایر شرایط.
            - اگر اطلاعاتی (مثل مبلغ دقیق یا تاریخ) داده نشده، به‌صورت واقع‌بینانه و قابل ویرایش یک مقدار نمونه بگذار و آن را در متن به‌گونه‌ای بنویس که کاربر بداند باید تکمیلش کند (مثلاً «مبلغ: [تعیین می‌شود]»).
            - در پایان متن قرارداد، هیچ توضیح یا جمع‌بندی اضافه ننویس.
            PROMPT;
    }

    private function deriveTitle(?ContractCategory $category, string $counterparty): string
    {
        return trim(($category?->name ? "قرارداد {$category->name}" : 'قرارداد')." با {$counterparty}");
    }
}
