<?php

namespace App\Filament\Pages\Concerns;

use App\Domain\Ai\AiPageContextResolver;
use App\Models\FormTemplate;
use Illuminate\Database\Eloquent\Model;

/**
 * Shared by every assistant engine: remembers which panel page / module /
 * record the user was on for the message being sent, and words that as a
 * system-prompt note.
 *
 * The floating widget lives on the module page itself and reports its own
 * URL ("current" context). The dedicated assistant pages (full-page chat,
 * hub chat) are not modules, so they fall back to the last module page the
 * user visited ("previous" context) - a weaker hint that is only used for
 * ambiguous questions and never to pick a record to change.
 */
trait ResolvesAiPageContext
{
    /**
     * Not public on purpose: recomputed server-side on every send and never
     * settable from the client.
     *
     * @var array{module: string, page: string, page_label: string, record: ?Model, record_title: ?string, resource: ?class-string}|null
     */
    protected ?array $pageContext = null;

    protected bool $pageContextIsPrevious = false;

    protected function capturePageContext(?string $pageUrl): void
    {
        $resolver = app(AiPageContextResolver::class);

        $this->pageContext = $resolver->resolve($pageUrl);
        $this->pageContextIsPrevious = false;

        if ($this->pageContext === null && blank($pageUrl)) {
            $this->pageContext = $resolver->previousContext();
            $this->pageContextIsPrevious = $this->pageContext !== null;
        }
    }

    private function pageContextNote(): ?string
    {
        if (! $this->pageContext) {
            return null;
        }

        $context = $this->pageContext;

        if ($this->pageContextIsPrevious) {
            $note = sprintf('کاربر پیش از باز کردن این گفتگو در ماژول «%s» (%s) بود', $context['module'], $context['page_label']);

            if ($context['record_title'] !== null) {
                $note .= sprintf(' و رکورد «%s» را باز کرده بود', $context['record_title']);
            }

            return $note.'. فقط اگر سؤال مبهم است و به‌نظر می‌رسد به همان ماژول مربوط است از این موقعیت استفاده کن؛ در غیر این صورت آن را نادیده بگیر و سؤال را عمومی فرض کن.';
        }

        $note = sprintf('موقعیت فعلی کاربر در پنل: ماژول «%s» (%s).', $context['module'], $context['page_label']);

        if ($context['record_title'] !== null) {
            $note .= sprintf(' رکوردی که کاربر الان باز کرده: «%s».', $context['record_title']);
        }

        $note .= ' سؤال‌های کاربر را به‌طور پیش‌فرض دربارهٔ همین ماژول و رکورد فرض کن و تمرکز پاسخ و ابزارهایت را روی همین بگذار، مگر اینکه کاربر آشکارا سؤال عمومی یا دربارهٔ بخش دیگری بپرسد (مثلاً «وضعیت کل کسب‌وکار» یا نام ماژول دیگر) - آن‌وقت بدون محدودیت از همهٔ ابزارها استفاده کن. اگر مطمئن نیستی منظورش همین ماژول است یا کلی، یک سؤال کوتاه بپرس.';

        $currentForm = $this->currentFormTemplate();

        if ($currentForm) {
            $note .= sprintf(' کاربر در حال کار روی فرم «%s» است: وقتی بدون اسم‌بردن از فرم می‌گوید «این فرم»، «همین فرم»، یا فیلد/متن سند اضافه یا عوض کن، ابزارهای add_field_to_form_template، set_form_document_template و get_form_template_fields را با form_template_name دقیقاً برابر «%s» صدا بزن، و برای ساخت فرم جدید ابزار design_form_template_draft را فقط وقتی صریحاً «فرم جدید» خواست.', $currentForm->title, $currentForm->title);
        }

        return $note;
    }

    /**
     * Only a form the user is looking at right now may become a tool
     * default - a remembered earlier page never is.
     */
    private function currentFormTemplate(): ?FormTemplate
    {
        if ($this->pageContextIsPrevious) {
            return null;
        }

        $record = $this->pageContext['record'] ?? null;

        return $record instanceof FormTemplate ? $record : null;
    }
}
