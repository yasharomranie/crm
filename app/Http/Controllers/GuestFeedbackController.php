<?php

namespace App\Http\Controllers;

use App\Models\GuestFeedback;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * The survey page behind the link texted after checkout. The link is signed
 * (so it cannot be guessed or edited) and expires; the guest answers once.
 */
class GuestFeedbackController extends Controller
{
    public function show(Request $request, GuestFeedback $feedback): View
    {
        if ($request->isMethod('post') && $feedback->submitted_at === null) {
            $data = $request->validate([
                'nps_score' => ['required', 'integer', 'between:0,10'],
                'comment' => ['nullable', 'string', 'max:1000'],
            ]);

            $feedback->update($data + ['submitted_at' => now()]);
        }

        return view('guest-feedback', [
            'feedback' => $feedback->loadMissing('guest'),
            'answered' => $feedback->submitted_at !== null,
        ]);
    }
}
