<?php

namespace App\Http\Controllers;

use App\Domain\Crm\LeadCaptureService;
use App\Models\LeadCaptureForm;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LeadCaptureController extends Controller
{
    public function __construct(private LeadCaptureService $leads) {}

    public function show(LeadCaptureForm $leadCaptureForm): View
    {
        abort_unless($leadCaptureForm->is_active, 404);

        return view('lead-capture.show', ['form' => $leadCaptureForm]);
    }

    public function store(Request $request, LeadCaptureForm $leadCaptureForm): RedirectResponse
    {
        abort_unless($leadCaptureForm->is_active, 404);

        $request->merge(['mobile' => LeadCaptureService::normalizeMobile((string) $request->input('mobile'))]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'mobile' => ['required', 'regex:/^09\d{9}$/'],
            'message' => ['nullable', 'string', 'max:2000'],
            'sms_consent' => ['nullable', 'boolean'],
        ], [
            'name.required' => 'نام خود را وارد کنید.',
            'mobile.required' => 'شماره‌ی موبایل را وارد کنید.',
            'mobile.regex' => 'شماره‌ی موبایل باید به شکل ۰۹۱۲۳۴۵۶۷۸۹ باشد.',
            'message.max' => 'پیام نباید بیشتر از ۲۰۰۰ نویسه باشد.',
        ]);

        // Hidden "website" field a real visitor never sees or fills: a bot
        // that does gets the same success screen but nothing is stored.
        if (! filled($request->input('website'))) {
            $this->leads->capture($leadCaptureForm, [...$data, 'sms_consent' => (bool) ($data['sms_consent'] ?? false)], $request->ip());
        }

        return redirect()->route('lead-capture.show', $leadCaptureForm->slug)->with('lead_submitted', true);
    }
}
