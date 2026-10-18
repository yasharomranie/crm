<?php

namespace App\Http\Controllers\CustomerApp;

use App\Enums\MessageChannel;
use App\Http\Controllers\Controller;
use App\Models\Guest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * "پروفایل کاربری" - where a signed-in guest lands after logging in (see
 * AuthController::login()), and the destination of the bottom nav's own
 * raised, round auth button when a guest is signed in (see
 * partials.customer-app-bottom-nav). Read-only account overview, reusing
 * Guest's existing loyalty/wallet logic exactly like WalletController does.
 */
class ProfileController extends Controller
{
    public function index(): View
    {
        /** @var Guest $guest */
        $guest = Auth::guard('customer')->user();

        return view('customer-app-profile', [
            'guest' => $guest,
            'tier' => $guest->loyaltyTier(),
            'smsConsent' => $guest->hasMarketingConsent(MessageChannel::Sms),
            'emailConsent' => $guest->hasMarketingConsent(MessageChannel::Email),
        ]);
    }

    public function updateConsents(Request $request): RedirectResponse
    {
        /** @var Guest $guest */
        $guest = Auth::guard('customer')->user();

        $guest->recordConsent(MessageChannel::Sms, $request->boolean('sms'), 'customer_app', null, null, $request->ip());
        $guest->recordConsent(MessageChannel::Email, $request->boolean('email'), 'customer_app', null, null, $request->ip());

        return back()->with('success', 'تنظیمات دریافت پیام ذخیره شد.');
    }
}
