<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * Renders a saved contract as a clean, print-ready page (no admin-panel
 * chrome) - the "متن قابل پرینت" the AI drafts and audits on the
 * "دستیار حقوقی و قرارداد" page. Authorization mirrors
 * DocumentDownloadController: reuses the dynamic access-control policy
 * already bound to the Contract model.
 */
class ContractPrintController extends Controller
{
    public function show(Request $request, Contract $contract): View
    {
        abort_unless($request->user()->can('view', $contract), 403, 'شما به این قرارداد دسترسی ندارید.');

        return view('contracts.print', ['contract' => $contract]);
    }

    /**
     * A genuine one-click download - no PDF/Word-generation library added:
     * Word opens HTML served with a .doc extension/MIME type natively, so
     * this is the same page's content in a Word-flavoured layout (see
     * resources/views/contracts/word.blade.php), forced to download.
     */
    public function exportWord(Request $request, Contract $contract): Response
    {
        abort_unless($request->user()->can('view', $contract), 403, 'شما به این قرارداد دسترسی ندارید.');

        $html = view('contracts.word', ['contract' => $contract])->render();

        // Persian titles don't survive Str::slug (it strips non-Latin
        // characters), so the real title is sent as the RFC 6266
        // filename* parameter - every modern browser uses it and saves
        // the file with its actual Persian name; the ASCII fallback only
        // matters for a user agent that doesn't understand filename*.
        $asciiFallback = Str::slug($contract->title, '-') ?: 'contract';
        $utf8Filename = rawurlencode("{$contract->title}.doc");

        return response($html, 200, [
            'Content-Type' => 'application/vnd.ms-word; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$asciiFallback}.doc\"; filename*=UTF-8''{$utf8Filename}",
        ]);
    }
}
