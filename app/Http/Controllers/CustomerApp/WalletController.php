<?php

namespace App\Http\Controllers\CustomerApp;

use App\Http\Controllers\Controller;
use App\Models\Guest;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;

/**
 * "کیف پول" و "باشگاه" tabs of the وب‌اپ مشتریان, combined on one page -
 * read-only reporting on top of Guest's existing wallet/loyalty logic
 * (creditWallet()/debitWallet() already run from staff actions like
 * Booking::checkOut()'s cashback; this page never writes to the wallet
 * itself, topping up online needs a payment gateway not yet connected).
 */
class WalletController extends Controller
{
    public function index(): View
    {
        /** @var Guest $guest */
        $guest = Auth::guard('customer')->user();

        return view('customer-app-wallet', [
            'guest' => $guest,
            'tier' => $guest->loyaltyTier(),
            'completedStays' => $guest->frequency(),
            'transactions' => $guest->walletTransactions()->latest()->get(),
        ]);
    }
}
