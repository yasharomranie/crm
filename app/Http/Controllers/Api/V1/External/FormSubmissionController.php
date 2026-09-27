<?php

namespace App\Http\Controllers\Api\V1\External;

use App\Enums\FormFieldType;
use App\Enums\FormSubmissionStatus;
use App\Filament\Support\DynamicFormFieldBuilder;
use App\Http\Controllers\Controller;
use App\Models\ExternalApiClient;
use App\Models\FormSubmission;
use App\Models\FormTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Lets an external site/app embed one of this panel's own form templates
 * and post answers in directly - the submission this creates goes through
 * the exact same submit() (workflow-start-or-auto-approve) as one filled
 * out inside the panel, so approval routing, the sales/legal workflow
 * engine, and archiving into مدیریت اسناد all behave identically either
 * way. File/Signature/GPS/Table fields aren't accepted here (they need
 * richer client-side capture than a plain JSON body can carry) - a
 * template that requires one of those can't be filled through this
 * endpoint.
 */
class FormSubmissionController extends Controller
{
    private const UNSUPPORTED_TYPES = [FormFieldType::File, FormFieldType::Signature, FormFieldType::Gps, FormFieldType::Table];

    public function store(Request $request, int $formTemplateId): JsonResponse
    {
        /** @var ExternalApiClient $client */
        $client = $request->attributes->get('external_api_client');

        if (! $client->hasPermission('form_submission.create')) {
            return $this->error('FORBIDDEN', 'این اکانت مجاز به ثبت فرم نیست.', 403);
        }

        $template = FormTemplate::with('fields')->where('is_active', true)->find($formTemplateId);

        if (! $template) {
            return $this->error('NOT_FOUND', 'قالب فرمی با این شناسه یافت نشد.', 404);
        }

        $data = $request->validate([
            'values' => ['sometimes', 'array'],
        ]);

        $values = $data['values'] ?? [];

        // The archive-to-Document pipeline (FormSubmissionObserver, once
        // this submission reaches Approved) requires a real uploaded_by -
        // an API-originated submission has no authenticated staff member
        // to attribute that to, so it borrows the user who was set as
        // this API client's own "submitted as" account instead.
        if (! $client->created_by) {
            return $this->error('CONFIGURATION_ERROR', 'برای این اکانت کاربر «ثبت به نام» تنظیم نشده است - از پنل مدیریت اکانت‌های API این را تنظیم کنید.', 422);
        }

        foreach ($template->fields as $field) {
            if (in_array($field->field_type, self::UNSUPPORTED_TYPES, true) && array_key_exists($field->field_key, $values)) {
                return $this->error('VALIDATION_ERROR', "فیلد «{$field->label}» از نوع {$field->field_type->getLabel()} از طریق این API پشتیبانی نمی‌شود.", 422);
            }

            if ($field->is_required && in_array($field->field_type, self::UNSUPPORTED_TYPES, true)) {
                return $this->error('VALIDATION_ERROR', "این فرم شامل فیلد الزامی «{$field->label}» از نوعی است که از طریق API قابل تکمیل نیست.", 422);
            }

            if ($field->is_required && blank($values[$field->field_key] ?? null)) {
                return $this->error('VALIDATION_ERROR', "فیلد «{$field->label}» الزامی است.", 422);
            }

            if (in_array($field->field_type, [FormFieldType::Select, FormFieldType::Radio], true)
                && array_key_exists($field->field_key, $values)
                && filled($field->options)
                && ! array_key_exists((string) $values[$field->field_key], $field->options)) {
                return $this->error('VALIDATION_ERROR', "مقدار فیلد «{$field->label}» معتبر نیست.", 422);
            }
        }

        $submission = DB::transaction(function () use ($template, $values, $client) {
            $submission = FormSubmission::create([
                'form_template_id' => $template->id,
                'form_template_version' => $template->version,
                'submitted_by' => $client->created_by,
                'department_id' => $template->department_id,
                'external_api_client_id' => $client->id,
                'status' => FormSubmissionStatus::Draft,
            ]);

            foreach ($template->fields as $field) {
                if (! array_key_exists($field->field_key, $values)) {
                    continue;
                }

                $submission->values()->create([
                    'form_field_id' => $field->id,
                    ...DynamicFormFieldBuilder::valueColumns($field, $values[$field->field_key]),
                ]);
            }

            $submission->submit();

            return $submission;
        });

        return response()->json([
            'submission_id' => $submission->id,
            'reference_code' => $submission->reference_code,
            'status' => $submission->status->value,
        ], 201);
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return response()->json(['error' => ['code' => $code, 'message' => $message]], $status);
    }
}
