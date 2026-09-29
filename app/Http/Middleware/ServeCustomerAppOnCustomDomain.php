<?php

namespace App\Http\Middleware;

use App\Http\Controllers\CustomerAppController;
use App\Models\WebAppDomainSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Lets an admin-connected custom domain (see WebAppDomainSetting +
 * App\Filament\Pages\WebAppDomainSettings) show the customer وب‌اپ at its
 * own clean root "/", instead of only under this app's own /app path.
 *
 * Runs on every request to "/" (cheap: one singleton lookup) but only
 * ever acts when the request's Host matches a domain that is both
 * enabled AND has actually passed DNS verification - typing a domain in
 * and saving it must never start silently serving app content there. Every
 * other request (including /app itself, on any host) passes through
 * untouched. A failure reading the setting (e.g. before migrations have
 * run) must never take down this app's own root page over an entirely
 * optional feature, so it falls through to the normal response instead.
 */
class ServeCustomerAppOnCustomDomain
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->path() !== '/') {
            return $next($request);
        }

        try {
            $isActive = WebAppDomainSetting::current()->isActiveFor($request->getHost());
        } catch (Throwable) {
            $isActive = false;
        }

        if (! $isActive) {
            return $next($request);
        }

        return response(app(CustomerAppController::class)->index());
    }
}
