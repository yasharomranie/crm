<?php

namespace App\Http\Controllers;

use App\Models\BookingPrecheckin;
use App\Models\Guest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The online pre-registration page a guest opens from the link they were
 * sent. The token is the only key (no login). The guest can only add to the
 * booking's information (arrival time, requests, companions, national code);
 * nothing about the stay, the price or the guest's contact details changes.
 */
class BookingPrecheckinController extends Controller
{
    private const DIGITS = [
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
        '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
        '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
    ];

    public function show(string $token): View
    {
        $precheckin = $this->find($token);

        return view('booking-precheckin', ['precheckin' => $precheckin, 'booking' => $precheckin->booking, 'open' => $precheckin->isOpen()]);
    }

    public function store(Request $request, string $token): RedirectResponse
    {
        $precheckin = $this->find($token);

        abort_unless($precheckin->isOpen(), 403, 'پیش‌ثبت‌نام این رزرو بسته شده است.');

        $request->merge($this->latinDigits($request->only(['national_code', 'arrival_time'])) + [
            'companions' => collect($request->input('companions', []))
                ->filter(fn ($row) => is_array($row) && (filled($row['name'] ?? null) || filled($row['national_code'] ?? null)))
                ->map(fn (array $row) => ['name' => $row['name'] ?? null] + $this->latinDigits(['national_code' => $row['national_code'] ?? null]))
                ->values()
                ->all(),
        ]);

        $data = $request->validate([
            'national_code' => ['required', 'digits:10'],
            'arrival_time' => ['nullable', 'date_format:H:i'],
            'requests' => ['nullable', 'string', 'max:1000'],
            'companions' => ['array', 'max:'.BookingPrecheckin::MAX_COMPANIONS],
            'companions.*.name' => ['required', 'string', 'max:255'],
            'companions.*.national_code' => ['nullable', 'digits:10'],
        ]);

        $precheckin->update([
            'national_code' => $data['national_code'],
            'arrival_time' => $data['arrival_time'] ?? null,
            'requests' => $data['requests'] ?? null,
            'companions' => $data['companions'],
            'completed_at' => now(),
        ]);

        $this->rememberNationalCode($precheckin->booking->guest, $data['national_code']);

        return redirect()->route('booking-precheckin.show', $token)->with('saved', true);
    }

    private function find(string $token): BookingPrecheckin
    {
        return BookingPrecheckin::query()->where('token', $token)->with('booking.guest', 'booking.room')->firstOrFail();
    }

    /**
     * The national code is unique per guest, so it is copied to the guest only
     * when the guest has none and nobody else already has this one.
     */
    private function rememberNationalCode(Guest $guest, string $nationalCode): void
    {
        if (blank($guest->national_code) && ! Guest::query()->where('national_code', $nationalCode)->exists()) {
            $guest->update(['national_code' => $nationalCode]);
        }
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function latinDigits(array $values): array
    {
        return array_map(fn ($value) => is_string($value) ? strtr(trim($value), self::DIGITS) : $value, $values);
    }
}
