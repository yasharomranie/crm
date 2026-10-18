<?php

namespace Tests\Feature\Crm;

use App\Domain\Crm\GuestDuplicateDetector;
use App\Models\Guest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GuestDuplicateDetectorTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return list<list<int>>
     */
    private function groupIds(): array
    {
        return app(GuestDuplicateDetector::class)->findGroups()
            ->map(fn (array $group) => $group['guests']->pluck('id')->all())
            ->all();
    }

    public function test_every_common_way_of_writing_one_mobile_number_normalizes_the_same(): void
    {
        foreach (['09121234567', '+989121234567', '00989121234567', '989121234567', '9121234567', '۰۹۱۲۱۲۳۴۵۶۷', '0912 123 4567', '0912-123-4567'] as $written) {
            $this->assertSame('09121234567', GuestDuplicateDetector::normalizeMobile($written), $written);
        }

        $this->assertNull(GuestDuplicateDetector::normalizeMobile('12345'));
        $this->assertNull(GuestDuplicateDetector::normalizeMobile(null));
    }

    public function test_the_same_mobile_written_differently_is_flagged(): void
    {
        $a = Guest::factory()->create(['name' => 'الف', 'mobile' => '09121234567']);
        $b = Guest::factory()->create(['name' => 'ب', 'mobile' => null]);
        DB::table('guests')->where('id', $b->id)->update(['mobile' => '+989121234567']);
        Guest::factory()->create(['name' => 'ج', 'mobile' => '09350000000']);

        $groups = app(GuestDuplicateDetector::class)->findGroups();

        $this->assertSame([[$a->id, $b->id]], $this->groupIds());
        $this->assertSame([GuestDuplicateDetector::REASON_MOBILE], $groups->first()['reasons']);
    }

    public function test_an_email_differing_only_in_case_or_spacing_is_flagged(): void
    {
        $a = Guest::factory()->create(['email' => 'Sara@Example.com']);
        $b = Guest::factory()->create(['email' => ' sara@example.com ']);

        $this->assertSame([[$a->id, $b->id]], $this->groupIds());
    }

    public function test_the_same_name_is_flagged_only_when_one_record_has_no_phone_to_tell_them_apart(): void
    {
        $withPhone = Guest::factory()->create(['name' => 'محمد رضا کریمی', 'mobile' => '09121111111']);
        $withoutPhone = Guest::factory()->create(['name' => 'محمدرضا كريمي', 'mobile' => null]);

        $this->assertSame([[$withPhone->id, $withoutPhone->id]], $this->groupIds());
    }

    public function test_two_same_named_guests_with_different_phones_are_assumed_to_be_different_people(): void
    {
        Guest::factory()->create(['name' => 'علی محمدی', 'mobile' => '09121111111']);
        Guest::factory()->create(['name' => 'علی محمدی', 'mobile' => '09352222222']);

        $this->assertSame([], $this->groupIds());
    }

    public function test_a_common_name_shared_by_three_or_more_guests_is_never_guessed_at(): void
    {
        Guest::factory()->count(3)->create(['name' => 'علی محمدی', 'mobile' => null]);

        $this->assertSame([], $this->groupIds());
    }

    public function test_records_matched_by_different_signals_collapse_into_one_group(): void
    {
        $a = Guest::factory()->create(['name' => 'الف', 'mobile' => '09121234567', 'email' => null]);
        $b = Guest::factory()->create(['name' => 'ب', 'mobile' => null, 'email' => 'x@example.com']);
        DB::table('guests')->where('id', $b->id)->update(['mobile' => '+989121234567']);
        $c = Guest::factory()->create(['name' => 'ج', 'mobile' => null, 'email' => 'X@example.com']);

        $this->assertSame([[$a->id, $b->id, $c->id]], $this->groupIds());
    }
}
