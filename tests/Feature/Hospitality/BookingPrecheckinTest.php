<?php

namespace Tests\Feature\Hospitality;

use App\Domain\Crm\BookingMessenger;
use App\Enums\BookingStatus;
use App\Filament\Pages\TodayArrivals;
use App\Filament\Resources\Bookings\Pages\ListBookings;
use App\Models\Booking;
use App\Models\BookingPrecheckin;
use App\Models\Guest;
use App\Models\SmsSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BookingPrecheckinTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin']));

        return $admin;
    }

    private function booking(BookingStatus $status = BookingStatus::Confirmed, array $overrides = []): Booking
    {
        return Booking::factory()->create($overrides + [
            'status' => $status,
            'check_in_date' => now()->addDays(5)->toDateString(),
            'check_out_date' => now()->addDays(7)->toDateString(),
            'adults_count' => 2,
            'children_count' => 1,
        ]);
    }

    public function test_a_booking_has_one_stable_link(): void
    {
        $booking = $this->booking();

        $first = BookingPrecheckin::forBooking($booking);
        $second = BookingPrecheckin::forBooking($booking);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(40, strlen($first->token));
        $this->assertSame(route('booking-precheckin.show', $first->token), $first->url());
        $this->assertFalse($first->isCompleted());
    }

    public function test_the_form_shows_the_booking_and_saves_what_the_guest_enters(): void
    {
        $booking = $this->booking(overrides: ['guest_id' => Guest::factory()->create(['national_code' => null])->id]);
        $precheckin = BookingPrecheckin::forBooking($booking);

        $this->get($precheckin->url())->assertOk()->assertSee($booking->reference_code)->assertSee($booking->guest->name);

        $this->post(route('booking-precheckin.store', $precheckin->token), [
            'national_code' => '۰۰۷۹۸۳۱۲۳۴',
            'arrival_time' => '۱۴:۳۰',
            'requests' => 'تخت اضافه',
            'companions' => [
                ['name' => 'سارا احمدی', 'national_code' => '0012345678'],
                ['name' => '', 'national_code' => ''],
            ],
        ])->assertRedirect($precheckin->url())->assertSessionHas('saved');

        $precheckin->refresh();
        $this->assertTrue($precheckin->isCompleted());
        $this->assertSame('0079831234', $precheckin->national_code);
        $this->assertSame('14:30', $precheckin->arrival_time);
        $this->assertSame('تخت اضافه', $precheckin->requests);
        $this->assertSame([['name' => 'سارا احمدی', 'national_code' => '0012345678']], $precheckin->companions);
        $this->assertSame('0079831234', $booking->guest->fresh()->national_code);
    }

    public function test_an_existing_national_code_of_the_guest_is_never_overwritten_and_a_taken_one_is_not_copied(): void
    {
        $booking = $this->booking(overrides: ['guest_id' => Guest::factory()->create(['national_code' => '1111111111'])->id]);
        $precheckin = BookingPrecheckin::forBooking($booking);

        $this->post(route('booking-precheckin.store', $precheckin->token), ['national_code' => '2222222222']);
        $this->assertSame('1111111111', $booking->guest->fresh()->national_code);
        $this->assertSame('2222222222', $precheckin->fresh()->national_code);

        $other = $this->booking(overrides: ['guest_id' => Guest::factory()->create(['national_code' => null])->id]);
        $otherPrecheckin = BookingPrecheckin::forBooking($other);
        Guest::factory()->create(['national_code' => '3333333333']);

        $this->post(route('booking-precheckin.store', $otherPrecheckin->token), ['national_code' => '3333333333'])->assertSessionHasNoErrors();
        $this->assertNull($other->guest->fresh()->national_code);
        $this->assertSame('3333333333', $otherPrecheckin->fresh()->national_code);
    }

    public function test_invalid_input_is_rejected_and_nothing_is_saved(): void
    {
        $precheckin = BookingPrecheckin::forBooking($this->booking());
        $url = route('booking-precheckin.store', $precheckin->token);

        $this->post($url, ['national_code' => '123'])->assertSessionHasErrors('national_code');
        $this->post($url, ['national_code' => '0079831234', 'arrival_time' => '25:99'])->assertSessionHasErrors('arrival_time');
        $this->post($url, ['national_code' => '0079831234', 'companions' => [['name' => '', 'national_code' => '0012345678']]])->assertSessionHasErrors('companions.0.name');
        $this->post($url, ['national_code' => '0079831234', 'companions' => array_fill(0, 11, ['name' => 'x'])])->assertSessionHasErrors('companions');

        $this->assertFalse($precheckin->fresh()->isCompleted());
    }

    public function test_the_form_is_closed_once_the_guest_is_in_the_house_or_the_booking_is_cancelled(): void
    {
        foreach ([BookingStatus::CheckedIn, BookingStatus::Cancelled] as $status) {
            $precheckin = BookingPrecheckin::forBooking($this->booking($status));

            $this->get($precheckin->url())->assertOk()->assertSee('بسته است');
            $this->post(route('booking-precheckin.store', $precheckin->token), ['national_code' => '0079831234'])->assertForbidden();
            $this->assertFalse($precheckin->fresh()->isCompleted());
        }
    }

    public function test_an_unknown_token_is_a_404(): void
    {
        $this->get(route('booking-precheckin.show', 'nope'))->assertNotFound();
    }

    public function test_a_message_template_can_carry_the_link(): void
    {
        $booking = $this->booking();

        $text = app(BookingMessenger::class)->render('برای ورود سریع: {precheckin_link}', $booking);

        $this->assertSame('برای ورود سریع: '.BookingPrecheckin::forBooking($booking)->url(), $text);
        $this->assertSame(1, BookingPrecheckin::count());
        $this->assertSame('بدون لینک', app(BookingMessenger::class)->render('بدون لینک', $booking));
        $this->assertSame(1, BookingPrecheckin::count());
    }

    public function test_staff_get_and_text_the_link_from_the_bookings_list(): void
    {
        Http::fake(['api.sms.ir/*' => Http::response(['status' => 1])]);
        SmsSetting::factory()->create();
        $booking = $this->booking();

        Livewire::actingAs($this->admin())
            ->test(ListBookings::class)
            ->callTableAction('precheckinLink', $booking, data: ['send_sms' => true])
            ->assertNotified('لینک پیش‌ثبت‌نام آماده است');

        $token = BookingPrecheckin::sole()->token;
        Http::assertSent(fn ($request) => str_contains($request->url(), 'api.sms.ir') && str_contains(json_encode($request->data()), $token));
    }

    public function test_the_front_desk_sees_what_the_guest_filled_in(): void
    {
        $booking = $this->booking(overrides: [
            'check_in_date' => now()->toDateString(),
            'check_out_date' => now()->addDay()->toDateString(),
        ]);
        $booking->precheckin()->create([
            'national_code' => '0079831234',
            'arrival_time' => '16:00',
            'requests' => 'گهواره برای نوزاد',
            'companions' => [['name' => 'سارا', 'national_code' => null]],
            'completed_at' => now(),
        ]);

        Livewire::actingAs($this->admin())
            ->test(TodayArrivals::class)
            ->assertSee('پیش‌ثبت‌نام انجام شده')
            ->assertSee('گهواره برای نوزاد');
    }
}
