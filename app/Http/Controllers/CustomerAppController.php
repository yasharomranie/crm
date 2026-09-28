<?php

namespace App\Http\Controllers;

use App\Models\WebAppBanner;
use App\Models\WebAppSection;
use Illuminate\Contracts\View\View;

class CustomerAppController extends Controller
{
    /**
     * The public, installable "خانه" screen of the گنجنامه customer app -
     * no login (this is the mockup's home screen only; there's no
     * customer account/API yet), content fully admin-managed via the
     * "وب‌اپ مشتریان" Filament resources.
     */
    public function index(): View
    {
        return view('customer-app', [
            'banners' => WebAppBanner::where('is_active', true)
                ->orderBy('sort_order')
                ->get(),
            'sections' => WebAppSection::where('is_active', true)
                ->orderBy('sort_order')
                ->with(['items' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order')])
                ->get(),
        ]);
    }
}
