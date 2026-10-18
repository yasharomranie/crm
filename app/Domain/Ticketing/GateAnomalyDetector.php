<?php

namespace App\Domain\Ticketing;

use App\Models\AdmissionTicket;
use App\Models\TicketUsageLog;
use App\Models\User;
use App\Notifications\SuspiciousTicketNotification;
use Illuminate\Support\Facades\Cache;

/**
 * Many failed scans of one ticket in a short time usually means a copied
 * or shared QR code being tried at several places (or a person trying
 * again and again). Managers get one alert per ticket per hour so they can
 * look at it - and block the ticket from the order page if it is a copy.
 */
class GateAnomalyDetector
{
    public const WINDOW_MINUTES = 10;

    public const MIN_REJECTIONS = 5;

    /**
     * @return list<array{ticket: AdmissionTicket, rejections: int, gates: int}> tickets that crossed the threshold
     */
    public function suspiciousTickets(): array
    {
        return TicketUsageLog::query()
            ->where('result', 'rejected')
            ->whereNotNull('admission_ticket_id')
            ->where('scanned_at', '>=', now()->subMinutes(self::WINDOW_MINUTES))
            ->groupBy('admission_ticket_id')
            ->havingRaw('count(*) >= ?', [self::MIN_REJECTIONS])
            ->selectRaw('admission_ticket_id, count(*) as rejections, count(distinct gate_id) as gates')
            ->get()
            ->map(fn ($row) => [
                'ticket' => AdmissionTicket::find($row->admission_ticket_id),
                'rejections' => (int) $row->rejections,
                'gates' => (int) $row->gates,
            ])
            ->filter(fn (array $suspect) => $suspect['ticket'] !== null)
            ->values()
            ->all();
    }

    /**
     * @return int how many tickets were reported to the admins
     */
    public function notifyAdmins(): int
    {
        $admins = User::query()->whereHas('roles', fn ($query) => $query->where('name', 'admin'))->get();
        $reported = 0;

        foreach ($this->suspiciousTickets() as $suspect) {
            // Cache::add only succeeds for the first caller in the hour.
            if (! Cache::add("gate-anomaly:{$suspect['ticket']->id}", true, now()->addHour())) {
                continue;
            }

            foreach ($admins as $admin) {
                $admin->notify(new SuspiciousTicketNotification($suspect['ticket'], $suspect['rejections'], $suspect['gates']));
            }

            $reported++;
        }

        return $reported;
    }
}
