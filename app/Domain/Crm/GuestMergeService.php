<?php

namespace App\Domain\Crm;

use App\Models\Guest;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Folds a duplicate guest record into the one being kept: every booking,
 * deal, ticket, wallet entry, feedback, trip plan, tag, follow-up and
 * timeline entry moves over, the wallet balances add up, blank contact
 * fields on the kept record are filled from the duplicate, and the
 * duplicate is deleted. All or nothing (one transaction); an audit entry
 * on the kept guest's timeline records exactly what was absorbed.
 */
class GuestMergeService
{
    /** Tables with a plain guest_id foreign key. */
    private const GUEST_ID_TABLES = [
        'bookings', 'wallet_transactions', 'deals', 'guest_feedbacks',
        'guest_tickets', 'guest_ticket_replies', 'trip_plans',
    ];

    /** Contact fields copied over only when the kept record has none. */
    private const FILLABLE_WHEN_BLANK = ['national_code', 'mobile', 'email', 'google_id', 'password', 'mobile_verified_at'];

    public function merge(Guest $keep, Guest $duplicate): Guest
    {
        if ($keep->is($duplicate)) {
            throw new InvalidArgumentException('یک پرونده را نمی‌توان با خودش ادغام کرد.');
        }

        DB::transaction(function () use ($keep, $duplicate) {
            $absorbed = $duplicate->only(['name', 'mobile', 'email', 'national_code']);

            foreach (self::GUEST_ID_TABLES as $table) {
                DB::table($table)->where('guest_id', $duplicate->id)->update(['guest_id' => $keep->id]);
            }

            $this->moveMorphs($keep, $duplicate);
            $this->moveTags($keep, $duplicate);

            // mobile/email/national_code/google_id are unique columns, so the
            // duplicate has to let go of its values before the kept record
            // can take any of them.
            $fresh = DB::table('guests')->where('id', $duplicate->id)->first();
            DB::table('guests')->where('id', $duplicate->id)->update(array_fill_keys(self::FILLABLE_WHEN_BLANK, null));

            $updates = [];

            foreach (self::FILLABLE_WHEN_BLANK as $field) {
                if (blank($keep->getRawOriginal($field)) && filled($fresh->{$field})) {
                    $updates[$field] = $fresh->{$field};
                }
            }

            $notes = collect([$keep->notes, $duplicate->notes])->filter(fn ($note) => filled($note))->implode("\n---\n");

            DB::table('guests')->where('id', $keep->id)->update([
                ...$updates,
                'notes' => $notes === '' ? null : $notes,
                'wallet_balance' => (int) $keep->wallet_balance + (int) $duplicate->wallet_balance,
                'updated_at' => now(),
            ]);

            $duplicate->deleteQuietly();

            activity('crm_interactions')
                ->performedOn($keep)
                ->causedBy(auth()->user())
                ->event('merged')
                ->withProperties(['absorbed_guest' => $absorbed])
                ->log('ادغام پرونده‌ی تکراری «'.$absorbed['name'].'»'.(filled($absorbed['mobile']) ? " ({$absorbed['mobile']})" : '').' در این پرونده');
        });

        return $keep->refresh();
    }

    private function moveMorphs(Guest $keep, Guest $duplicate): void
    {
        DB::table('activity_log')
            ->where('subject_type', Guest::class)->where('subject_id', $duplicate->id)
            ->update(['subject_id' => $keep->id]);

        DB::table('tasks')
            ->where('source_type', Guest::class)->where('source_id', $duplicate->id)
            ->update(['source_id' => $keep->id]);
    }

    /**
     * taggables is unique on (tag, record), so a tag both guests already
     * had must be dropped from the duplicate rather than re-pointed.
     */
    private function moveTags(Guest $keep, Guest $duplicate): void
    {
        $keptTagIds = DB::table('taggables')
            ->where('taggable_type', Guest::class)->where('taggable_id', $keep->id)
            ->pluck('tag_id');

        DB::table('taggables')
            ->where('taggable_type', Guest::class)->where('taggable_id', $duplicate->id)
            ->whereIn('tag_id', $keptTagIds)
            ->delete();

        DB::table('taggables')
            ->where('taggable_type', Guest::class)->where('taggable_id', $duplicate->id)
            ->update(['taggable_id' => $keep->id]);
    }
}
