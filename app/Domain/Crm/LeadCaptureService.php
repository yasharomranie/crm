<?php

namespace App\Domain\Crm;

use App\Enums\DealStage;
use App\Enums\MessageChannel;
use App\Models\Deal;
use App\Models\Guest;
use App\Models\LeadCaptureForm;
use App\Support\MobileNumber;

/**
 * Turns one public-form submission into the same records a salesperson
 * would create by hand: the guest (matched on mobile, never duplicated or
 * renamed if they already exist), a NewLead deal on the kanban board, and
 * a timeline entry on the guest's profile.
 */
class LeadCaptureService
{
    /**
     * @param  array{name: string, mobile: string, message?: string|null, sms_consent?: bool}  $data
     */
    public function capture(LeadCaptureForm $form, array $data, ?string $ip = null): Deal
    {
        $guest = Guest::firstOrCreate(
            ['mobile' => MobileNumber::canonical($data['mobile'])],
            ['name' => $data['name']],
        );

        if (! empty($data['sms_consent'])) {
            $guest->recordConsent(MessageChannel::Sms, true, 'lead_form', "فرم «{$form->name}»", null, $ip);
        }

        $deal = Deal::create([
            'guest_id' => $guest->id,
            'title' => "{$form->name} — {$guest->name}",
            'stage' => DealStage::NewLead,
            'source' => $form->deal_source,
            'assigned_to' => $form->assigned_to,
            'lead_capture_form_id' => $form->id,
            'notes' => $data['message'] ?? null,
        ]);

        activity('crm_interactions')
            ->performedOn($guest)
            ->withProperties(['deal_id' => $deal->id, 'lead_capture_form_id' => $form->id])
            ->event('lead_captured')
            ->log("ثبت درخواست از فرم «{$form->name}»");

        return $deal;
    }

    /**
     * Visitors often type Persian or Arabic-Indic digits - converted so the
     * same person isn't stored (and matched) under two spellings of one number.
     */
    public static function normalizeMobile(string $mobile): string
    {
        return strtr(trim($mobile), [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);
    }
}
