<?php

namespace App\Http\Controllers;

use App\Domain\Payments\BookingPaymentLinkService;
use App\Domain\Payments\GatewayException;
use App\Models\BookingPaymentLink;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The page a guest opens from a payment link to pay a booking online. The
 * token in the URL is the only key (no login); the callback is opened by the
 * gateway's redirect and everything it does is decided by the gateway's own
 * verification.
 */
class BookingPaymentController extends Controller
{
    public function __construct(private readonly BookingPaymentLinkService $links) {}

    public function show(string $token): View
    {
        $link = BookingPaymentLink::query()->where('token', $token)->with('booking.guest', 'booking.room')->firstOrFail();

        return view('booking-pay', ['link' => $link, 'booking' => $link->booking]);
    }

    public function start(string $token): RedirectResponse
    {
        $link = BookingPaymentLink::query()->where('token', $token)->with('booking')->firstOrFail();

        try {
            $url = $this->links->start($link, route('booking-pay.callback'));
        } catch (GatewayException $exception) {
            return redirect()->route('booking-pay.show', $token)->withErrors(['payment' => $exception->getMessage()]);
        }

        return redirect()->away($url);
    }

    public function callback(Request $request): RedirectResponse
    {
        $link = $this->links->complete((string) $request->query('Authority', ''), (string) $request->query('Status', ''));

        abort_if($link === null, 404);

        $redirect = redirect()->route('booking-pay.show', $link->token);

        return match (true) {
            $link->status === BookingPaymentLink::STATUS_PAID => $redirect,
            $link->error !== null => $redirect->withErrors(['payment' => $link->error]),
            default => $redirect->withErrors(['payment' => 'تأیید پرداخت هنوز ممکن نشد. اگر مبلغ از حساب شما کسر شده، چند دقیقه بعد همین صفحه را دوباره باز کنید.']),
        };
    }
}
