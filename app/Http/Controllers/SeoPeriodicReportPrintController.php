<?php

namespace App\Http\Controllers;

use App\Models\SeoPeriodicReport;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Re-serves a periodic SEO report's stored HTML as its own print-ready
 * page (no admin-panel chrome) - same "no PDF library, browser does the
 * print-to-PDF" convention as ContractPrintController/ReportPrintController.
 * Authorization reuses the dynamic access-control policy already bound to
 * SeoPeriodicReport (see config/access-control.php).
 */
class SeoPeriodicReportPrintController extends Controller
{
    public function show(Request $request, SeoPeriodicReport $seoPeriodicReport): Response
    {
        abort_unless($request->user()->can('view', $seoPeriodicReport), 403, 'شما به این گزارش دسترسی ندارید.');

        return response($seoPeriodicReport->html);
    }
}
