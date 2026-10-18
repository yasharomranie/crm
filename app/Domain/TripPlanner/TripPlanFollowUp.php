<?php

namespace App\Domain\TripPlanner;

use App\Domain\Crm\GuestMessenger;
use App\Enums\BookingStatus;
use App\Enums\MessageDeliveryStatus;
use App\Enums\TaskType;
use App\Models\Guest;
use App\Models\Task;
use App\Models\TripPlan;
use App\Models\TripPlannerSetting;
use App\Models\User;

/**
 * What a trip plan means to the business. A plan is a strong sign of
 * intent to visit, so it lands on the guest's CRM timeline; optionally
 * (a setting) staff get a follow-up task when the guest has planned a trip
 * but not booked a stay, and the guest gets an SMS the day before.
 */
class TripPlanFollowUp
{
    private const FOLLOW_UP_TITLE_PREFIX = 'پیگیری برنامه سفر';

    public function afterPlanCreated(TripPlan $plan): void
    {
        $guest = $plan->guest;

        activity('crm_interactions')
            ->performedOn($guest)
            ->event('trip_plan_created')
            ->withProperties(['trip_plan_id' => $plan->id, 'days' => $plan->days, 'starts_on' => $plan->starts_on?->toDateString()])
            ->log("برنامه‌ی سفر {$plan->days} روزه‌ی همدان ساخت");

        if (TripPlannerSetting::current()->create_followup_tasks) {
            $this->createFollowUpIfNoBooking($plan, $guest);
        }
    }

    /**
     * Texts every guest whose trip starts tomorrow (once).
     *
     * @return int number of reminders actually sent
     */
    public function sendDayBeforeReminders(): int
    {
        if (! TripPlannerSetting::current()->send_reminders) {
            return 0;
        }

        $sent = 0;

        TripPlan::query()
            ->ready()
            ->with('guest')
            ->whereDate('starts_on', now()->addDay()->toDateString())
            ->whereNull('reminder_sent_at')
            ->get()
            ->each(function (TripPlan $plan) use (&$sent) {
                $message = app(GuestMessenger::class)->sendSms($plan->guest, sprintf(
                    '%s عزیز، سفر %s روزه‌ی شما به همدان فردا شروع می‌شود. برنامه‌تان: %s',
                    $plan->guest->name,
                    $plan->days,
                    route('customer-app.trip-planner.show', $plan),
                ));

                if ($message->status === MessageDeliveryStatus::Sent) {
                    $plan->update(['reminder_sent_at' => now()]);
                    $sent++;
                }
            });

        return $sent;
    }

    private function createFollowUpIfNoBooking(TripPlan $plan, Guest $guest): void
    {
        $startsOn = ($plan->starts_on ?? $plan->created_at)->copy()->startOfDay();
        $endsOn = $startsOn->copy()->addDays($plan->days - 1);

        $hasBooking = $guest->bookings()
            ->where('status', '!=', BookingStatus::Cancelled)
            ->where('check_in_date', '<=', $endsOn)
            ->where('check_out_date', '>=', $startsOn)
            ->exists();

        $alreadyFollowedUp = $guest->followUpTasks()
            ->where('title', 'like', self::FOLLOW_UP_TITLE_PREFIX.'%')
            ->where('created_at', '>=', now()->subWeek())
            ->exists();

        if ($hasBooking || $alreadyFollowedUp) {
            return;
        }

        $owner = $guest->deals()->whereNotNull('assigned_to')->latest('id')->value('assigned_to')
            ?? User::query()->whereHas('roles', fn ($query) => $query->where('name', 'admin'))->value('id');

        Task::create([
            'title' => self::FOLLOW_UP_TITLE_PREFIX.": «{$guest->name}» برنامه‌ی سفر {$plan->days} روزه ساخته ولی هنوز اقامتی رزرو نکرده",
            'task_type' => TaskType::CrmFollowUp,
            'source_type' => Guest::class,
            'source_id' => $guest->id,
            'assigned_to' => $owner,
            'due_at' => now()->addDay(),
        ]);
    }
}
