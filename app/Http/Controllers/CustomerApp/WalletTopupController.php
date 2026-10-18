<?php

namespace App\Http\Controllers\CustomerApp;

use App\Domain\Payments\GatewayException;
use App\Domain\Payments\WalletTopupService;
use App\Http\Controllers\Controller;
use App\Models\Guest;
use App\Models\WalletTopup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Online top-up of the customer's own wallet through the payment gateway.
 * The callback is deliberately outside the login group: it is opened by the
 * gateway's redirect and everything it does is decided by the gateway's own
 * verification, never by who happens to be logged in.
 */
class WalletTopupController extends Controller
{
    public function __construct(private readonly WalletTopupService $topups) {}

    public function store(Request $request): RedirectResponse
    {
        /** @var Guest $guest */
        $guest = Auth::guard('customer')->user();

        $data = $request->validate(['amount' => ['required', 'integer', 'min:1']]);

        try {
            $url = $this->topups->start($guest, (int) $data['amount'], route('customer-app.wallet.topup.callback'));
        } catch (GatewayException $exception) {
            return redirect()->route('customer-app.wallet')->withErrors(['amount' => $exception->getMessage()]);
        }

        return redirect()->away($url);
    }

    public function callback(Request $request): RedirectResponse
    {
        $topup = $this->topups->complete((string) $request->query('Authority', ''), (string) $request->query('Status', ''));

        $redirect = redirect()->route('customer-app.wallet');

        return match ($topup?->status) {
            WalletTopup::STATUS_PAID => $redirect->with('success', 'کیف پول شما با موفقیت شارژ شد.'),
            WalletTopup::STATUS_PENDING => $redirect->withErrors(['amount' => 'تأیید پرداخت هنوز ممکن نشد. اگر مبلغ از حساب شما کسر شده، چند دقیقه بعد همین صفحه را دوباره باز کنید.']),
            default => $redirect->withErrors(['amount' => $topup?->error ?: 'پرداخت ناموفق بود.']),
        };
    }
}
