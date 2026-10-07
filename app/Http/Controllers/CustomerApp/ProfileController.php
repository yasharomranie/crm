<?php

namespace App\Http\Controllers\CustomerApp;

use App\Http\Controllers\Controller;
use App\Models\Guest;
use Illuminate\Contracts\View\View;
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
        ]);
    }
}
