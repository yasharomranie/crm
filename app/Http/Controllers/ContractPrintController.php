<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

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
}
