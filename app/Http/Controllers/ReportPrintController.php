<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * Serves the one-off HTML page TableExports::pdf() renders and caches at
 * click-time (see that class for why: it needs the table's *currently
 * filtered* rows, which only exist inside that Livewire request). The
 * token is a random UUID with a short TTL, and only the user who generated
 * it can view it - same spirit as the contract/form print routes' own
 * per-user access check, just keyed by a cache token instead of a model.
 */
class ReportPrintController extends Controller
{
    public function show(Request $request, string $token): Response
    {
        $cached = Cache::get("report-print:{$token}");

        abort_if($cached === null, 410, 'این گزارش منقضی شده است. لطفاً دوباره خروجی PDF را بگیرید.');
        abort_unless($cached['user_id'] === $request->user()->id, 403, 'شما به این گزارش دسترسی ندارید.');

        return response($cached['html']);
    }
}
