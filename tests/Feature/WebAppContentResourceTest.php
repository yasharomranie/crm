<?php

namespace Tests\Feature;

use App\Enums\WebAppTileAccent;
use App\Filament\Resources\WebAppBanners\Pages\ManageWebAppBanners;
use App\Filament\Resources\WebAppSections\Pages\ManageWebAppSections;
use App\Models\User;
use App\Models\WebAppBanner;
use App\Models\WebAppSection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WebAppContentResourceTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin']));

        return $admin;
    }

    public function test_the_banners_page_lists_banners(): void
    {
        $banner = WebAppBanner::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(ManageWebAppBanners::class)
            ->assertCanSeeTableRecords([$banner]);
    }

    public function test_a_banner_can_be_created_with_an_uploaded_image(): void
    {
        Storage::fake('public');

        Livewire::actingAs($this->admin())
            ->test(ManageWebAppBanners::class)
            ->mountAction('create')
            ->setActionData([
                'image_path' => UploadedFile::fake()->image('banner.jpg'),
                'title' => 'کمپین زمستانه',
                'subtitle' => 'اسکی با تخفیف ویژه',
                'sort_order' => 0,
                'is_active' => true,
            ])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $banner = WebAppBanner::where('title', 'کمپین زمستانه')->firstOrFail();

        Storage::disk('public')->assertExists($banner->image_path);
    }

    public function test_the_sections_page_lists_sections_with_item_counts(): void
    {
        $section = WebAppSection::factory()->create();
        $section->items()->create([
            'label' => 'آیتم تستی',
            'accent' => WebAppTileAccent::Orange,
        ]);

        Livewire::actingAs($this->admin())
            ->test(ManageWebAppSections::class)
            ->assertCanSeeTableRecords([$section]);
    }

    public function test_a_section_can_be_created_with_multiple_items_via_the_repeater(): void
    {
        Livewire::actingAs($this->admin())
            ->test(ManageWebAppSections::class)
            ->mountAction('create')
            ->setActionData([
                'title' => 'سفر و اقامت',
                'sort_order' => 0,
                'is_active' => true,
                'items' => [
                    ['label' => 'بلیت و چک‌این', 'accent' => WebAppTileAccent::Orange->value, 'sort_order' => 0, 'is_active' => true],
                    ['label' => 'رزرو اقامت', 'accent' => WebAppTileAccent::Green->value, 'sort_order' => 1, 'is_active' => true],
                ],
            ])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $section = WebAppSection::where('title', 'سفر و اقامت')->firstOrFail();

        $this->assertCount(2, $section->items);
        $this->assertEqualsCanonicalizing(
            ['بلیت و چک‌این', 'رزرو اقامت'],
            $section->items->pluck('label')->all(),
        );
    }
}
