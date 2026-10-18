<?php

namespace App\Http\Controllers\CustomerApp;

use App\Http\Controllers\Controller;
use App\Models\Guest;
use App\Models\PaymentGatewaySetting;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;

/**
 * "کیف پول" و "باشگاه" tabs of the وب‌اپ مشتریان, combined on one page -
 * read-only reporting on top of Guest's existing wallet/loyalty logic
 * (creditWallet()/debitWallet() already run from staff actions like
 * Booking::checkOut()'s cashback; this page never writes to the wallet
 * itself - online top-ups are handled by WalletTopupController).
 */
class WalletController extends Controller
{
    public function index(): View
    {
        /** @var Guest $guest */
        $guest = Auth::guard('customer')->user();

        $gateway = PaymentGatewaySetting::current();

        return view('customer-app-wallet', [
            'canTopUp' => $gateway->isUsable(),
            'minTopUp' => $gateway->min_topup_amount,
            'maxTopUp' => $gateway->max_topup_amount,
            'guest' => $guest,
            'tier' => $guest->loyaltyTier(),
            'completedStays' => $guest->frequency(),
            'transactions' => $guest->walletTransactions()->latest()->get(),
        ]);
    }
}
