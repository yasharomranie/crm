<?php

namespace Database\Seeders;

use App\Enums\WebAppTileAccent;
use App\Models\WebAppBanner;
use App\Models\WebAppSection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class WebAppContentSeeder extends Seeder
{
    /**
     * Demo content for the customer app's "خانه" screen, so /app isn't
     * empty on first run - real content is meant to replace this via the
     * "وب‌اپ مشتریان" admin resources, not to be hand-edited here.
     */
    public function run(): void
    {
        // The banner image itself has to be copied in from a versioned
        // source (resources/images/...) rather than assumed already
        // present - storage/app/public is gitignored, so a path only
        // ever written there (e.g. by a one-off local script) would be
        // missing on any fresh clone or CI run.
        $imagePath = 'web-app/banners/demo-autumn-campaign.jpg';

        if (! Storage::disk('public')->exists($imagePath)) {
            Storage::disk('public')->put(
                $imagePath,
                file_get_contents(resource_path('images/web-app/demo-autumn-campaign.jpg')),
            );
        }

        WebAppBanner::firstOrCreate(
            ['title' => 'پکیج پاییزه'],
            [
                'subtitle' => 'تله‌کابین + غار، با ۲۰٪ تخفیف ویژه',
                'image_path' => $imagePath,
                'sort_order' => 0,
                'is_active' => true,
            ],
        );

        $sections = [
            'سفر و اقامت' => [
                'بلیت و چک‌این',
                'رزرو اقامت',
                'تریپ‌پلنر',
                'سفرنامه',
            ],
            'امتیاز و پیشنهاد' => [
                'باشگاه مشتریان',
                'کیف پول و امتیاز',
                'کد تخفیف',
                'دعوت دوستان',
            ],
            'پشتیبانی و سرگرمی' => [
                'تیکت پشتیبانی',
                'نظرسنجی و بازخورد',
                'فال و بازی',
                'اطلاعیه‌ها',
            ],
        ];

        foreach (array_keys($sections) as $sectionIndex => $title) {
            $section = WebAppSection::firstOrCreate(
                ['title' => $title],
                ['sort_order' => $sectionIndex, 'is_active' => true],
            );

            foreach ($sections[$title] as $itemIndex => $label) {
                $section->items()->firstOrCreate(
                    ['label' => $label],
                    [
                        'accent' => $itemIndex % 2 === 0 ? WebAppTileAccent::Orange : WebAppTileAccent::Green,
                        'sort_order' => $itemIndex,
                        'is_active' => true,
                    ],
                );
            }
        }
    }
}
