<?php

namespace App\Console\Commands;

use App\Enums\AttendanceStatus;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

#[Signature('attendance:mark-absentees')]
#[Description('Create an "absent" attendance record for every user with an assigned work shift who never checked in today')]
class MarkAbsentees extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Simplified weekend rule: Friday is the only day skipped. There's
        // no per-organization holiday calendar yet, so a real public
        // holiday still gets everyone marked absent - acceptable for now,
        // but worth a holiday-calendar follow-up before relying on this
        // for payroll.
        if (Carbon::now()->isFriday()) {
            $this->info('امروز جمعه است؛ غیبتی ثبت نشد.');

            return self::SUCCESS;
        }

        $today = Carbon::today();

        $users = User::query()
            ->whereNotNull('work_shift_id')
            ->where('is_active', true)
            ->get();

        $marked = 0;

        foreach ($users as $user) {
            $hasCheckedIn = $user->attendanceRecords()
                ->whereDate('date', $today)
                ->whereNotNull('check_in_at')
                ->exists();

            if ($hasCheckedIn) {
                continue;
            }

            $user->attendanceRecords()->updateOrCreate(
                ['date' => $today->toDateString()],
                [
                    'work_shift_id' => $user->work_shift_id,
                    'status' => AttendanceStatus::Absent,
                ],
            );

            $marked++;
        }

        $this->info("غیبت {$marked} نفر از {$users->count()} نفر دارای شیفت ثبت شد.");

        return self::SUCCESS;
    }
}
