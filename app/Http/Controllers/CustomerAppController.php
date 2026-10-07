<?php

namespace App\Http\Controllers;

use App\Models\WebAppBanner;
use App\Models\WebAppSection;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;

class CustomerAppController extends Controller
{
    /**
     * The public, installable "خانه" screen of the گنجنامه customer app -
     * stays open to anonymous visitors (content is fully admin-managed via
     * the "وب‌اپ مشتریان" Filament resources), but the "guest" variable lets
     * the view route the account-specific tabs (سفارش‌های من/کیف پول/باشگاه)
     * to login when signed out, or to the real feature once Phase 2-4 land.
     */
    public function index(): View
    {
        return view('customer-app', [
            'guest' => Auth::guard('customer')->user(),
            'banners' => WebAppBanner::where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(),
            'sections' => WebAppSection::where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->with(['items' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order')->orderBy('id')])
                ->get(),
        ]);
    }
}
