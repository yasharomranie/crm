<?php

namespace Tests\Feature;

use App\Models\WebAppBanner;
use App\Models\WebAppSection;
use App\Models\WebAppSectionItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerAppPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_public_app_page_renders_active_banners_and_section_items(): void
    {
        WebAppBanner::factory()->create(['title' => 'کمپین پاییزه']);

        $section = WebAppSection::factory()->create(['title' => 'سفر و اقامت']);
        WebAppSectionItem::factory()->create([
            'web_app_section_id' => $section->id,
            'label' => 'بلیت و چک‌این',
        ]);

        $this->get('/app')
            ->assertOk()
            ->assertSee('کمپین پاییزه')
            ->assertSee('سفر و اقامت')
            ->assertSee('بلیت و چک‌این');
    }

    public function test_inactive_banners_sections_and_items_are_hidden_from_the_public_page(): void
    {
        WebAppBanner::factory()->create(['title' => 'بنر غیرفعال', 'is_active' => false]);

        $inactiveSection = WebAppSection::factory()->create(['title' => 'بخش غیرفعال', 'is_active' => false]);
        WebAppSectionItem::factory()->create([
            'web_app_section_id' => $inactiveSection->id,
            'label' => 'آیتم بخش غیرفعال',
        ]);

        $activeSection = WebAppSection::factory()->create(['title' => 'بخش فعال']);
        WebAppSectionItem::factory()->create([
            'web_app_section_id' => $activeSection->id,
            'label' => 'آیتم غیرفعال',
            'is_active' => false,
        ]);

        $response = $this->get('/app')->assertOk();

        $response->assertDontSee('بنر غیرفعال');
        $response->assertDontSee('بخش غیرفعال');
        $response->assertDontSee('آیتم بخش غیرفعال');
        $response->assertSee('بخش فعال');
        $response->assertDontSee('آیتم غیرفعال');
    }

    public function test_the_public_app_page_shows_an_empty_state_with_no_content(): void
    {
        $this->get('/app')
            ->assertOk()
            ->assertSee('هنوز محتوایی برای این صفحه از پنل مدیریت ثبت نشده است.');
    }
}
