<?php

namespace Tests\Feature\Crm;

use App\Domain\Crm\GuestMobileNormalizer;
use App\Models\Guest;
use App\Models\GuestOtpCode;
use App\Support\MobileNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MobileNormalizationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: string}>
     */
    public static function spellings(): array
    {
        return [
            'local' => ['09121234567'],
            'plus country code' => ['+989121234567'],
            'double zero country code' => ['00989121234567'],
            'country code' => ['989121234567'],
            'no leading zero' => ['9121234567'],
            'persian digits' => ['۰۹۱۲۱۲۳۴۵۶۷'],
            'arabic digits' => ['٠٩١٢١٢٣٤٥٦٧'],
            'spaces and dashes' => [' 0912-123 4567 '],
        ];
    }

    #[DataProvider('spellings')]
    public function test_every_spelling_of_a_mobile_normalises_to_the_same_number(string $spelling): void
    {
        $this->assertSame('09121234567', MobileNumber::normalize($spelling));
        $this->assertSame('09121234567', MobileNumber::canonical($spelling));
    }

    public function test_something_that_is_not_an_iranian_mobile_is_not_normalised(): void
    {
        $this->assertNull(MobileNumber::normalize('02112345678'));
        $this->assertNull(MobileNumber::normalize(null));
        $this->assertNull(MobileNumber::canonical('   '));
        $this->assertSame('+442071234567', MobileNumber::canonical(' +442071234567 '));
    }

    public function test_the_search_fragment_is_the_last_ten_digits_of_a_mobile(): void
    {
        $this->assertSame('9121234567', MobileNumber::searchFragment('+98 912 123 4567'));
        $this->assertSame('علی', MobileNumber::searchFragment(' علی '));
    }

    public function test_the_guest_model_stores_the_canonical_mobile(): void
    {
        $guest = Guest::factory()->create(['mobile' => '+989121234567']);

        $this->assertSame('09121234567', $guest->fresh()->mobile);
    }

    public function test_an_otp_login_with_another_spelling_reuses_the_existing_guest(): void
    {
        $guest = Guest::factory()->create(['mobile' => '09120000021']);

        $this->post(route('customer-app.auth.otp.request'), ['mobile' => '+989120000021']);
        $code = GuestOtpCode::where('mobile', '09120000021')->latest('id')->firstOrFail();

        $this->post(route('customer-app.auth.otp.verify'), ['mobile' => '+989120000021', 'code' => $code->code]);

        $this->assertAuthenticatedAs($guest, 'customer');
        $this->assertSame(1, Guest::count());
    }

    public function test_the_normalizer_reports_without_writing_unless_applied(): void
    {
        $guest = Guest::factory()->create();
        DB::table('guests')->where('id', $guest->id)->update(['mobile' => '+989121234567']);

        $dry = (new GuestMobileNormalizer)->run(false);

        $this->assertSame([['id' => $guest->id, 'from' => '+989121234567', 'to' => '09121234567']], $dry['changed']);
        $this->assertSame('+989121234567', DB::table('guests')->where('id', $guest->id)->value('mobile'));

        (new GuestMobileNormalizer)->run(true);

        $this->assertSame('09121234567', DB::table('guests')->where('id', $guest->id)->value('mobile'));
    }

    public function test_the_normalizer_leaves_two_spellings_of_one_number_for_a_manual_merge(): void
    {
        $kept = Guest::factory()->create(['mobile' => '09121234567']);
        $other = Guest::factory()->create();
        DB::table('guests')->where('id', $other->id)->update(['mobile' => '+989121234567']);

        $result = (new GuestMobileNormalizer)->run(true);

        $this->assertSame([], $result['changed']);
        $this->assertSame(
            [['id' => $other->id, 'mobile' => '+989121234567', 'canonical' => '09121234567', 'other_id' => $kept->id]],
            $result['conflicts'],
        );
        $this->assertSame('+989121234567', DB::table('guests')->where('id', $other->id)->value('mobile'));
    }

    public function test_the_command_defaults_to_a_dry_run(): void
    {
        $guest = Guest::factory()->create();
        DB::table('guests')->where('id', $guest->id)->update(['mobile' => '9121234567']);

        $this->artisan('guests:normalize-mobiles')->assertSuccessful();
        $this->assertSame('9121234567', DB::table('guests')->where('id', $guest->id)->value('mobile'));

        $this->artisan('guests:normalize-mobiles --apply')->assertSuccessful();
        $this->assertSame('09121234567', DB::table('guests')->where('id', $guest->id)->value('mobile'));
    }
}
