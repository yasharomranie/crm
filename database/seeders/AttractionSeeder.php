<?php

namespace Database\Seeders;

use App\Enums\AttractionCategory;
use App\Models\Attraction;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Real, well-known Hamedan tourist sites, so "تریپ‌پلنر" has something to
 * actually schedule a guest into on first run - real content is meant to
 * be reviewed/extended from "جاذبه‌های گردشگری" in the admin panel, not
 * hand-edited here.
 *
 * Coordinates were cross-checked against public sources where a specific
 * site could be pinned (e.g. Ali Sadr Cave, the Ganjnameh inscriptions,
 * the Avicenna Mausoleum, Hamadan's own city-center coordinates) - the
 * rest are reasonable estimates relative to those verified points, not
 * surveyed pins. Every row should still be opened once in "جاذبه‌های
 * گردشگری" and its marker checked/adjusted before the "مسیریابی" link is
 * trusted for a real guest's navigation.
 */
class AttractionSeeder extends Seeder
{
    public function run(): void
    {
        $attractions = [
            [
                'name' => 'غار علی‌صدر',
                'category' => AttractionCategory::Nature,
                'description' => 'بزرگ‌ترین غار آبی ایران و یکی از مقاصد شاخص گردشگری کشور - قایق‌سواری در دل تالارهای آهکی، حدود یک ساعت رانندگی از شهر همدان.',
                'lat' => 35.3016111,
                'lng' => 48.3038806,
                'address' => 'روستای علی‌صدر، کبودراهنگ، استان همدان',
                'avg_visit_minutes' => 150,
                'opens_at' => '08:00',
                'closes_at' => '18:00',
                'boost_weight' => 2,
            ],
            [
                'name' => 'آبشار و سنگ‌نگاره‌های گنجنامه',
                'category' => AttractionCategory::Historical,
                'description' => 'دو کتیبه هخامنشی از داریوش بزرگ و خشایارشا بر دل کوه الوند، کنار آبشاری خنک - مسیر پیاده‌روی کوتاه و مناسب خانواده.',
                'lat' => 34.7634,
                'lng' => 48.4031,
                'address' => 'دامنه کوه الوند، غرب همدان',
                'avg_visit_minutes' => 90,
                'boost_weight' => 1,
            ],
            [
                'name' => 'تله‌کابین الوند',
                'category' => AttractionCategory::Adventure,
                'description' => 'مسیر هوایی کوتاه از دامنه الوند تا نزدیکی گنجنامه، با چشم‌اندازی از شهر همدان.',
                'lat' => 34.7650,
                'lng' => 48.4050,
                'address' => 'دامنه کوه الوند، نزدیک گنجنامه',
                'avg_visit_minutes' => 45,
                'opens_at' => '09:00',
                'closes_at' => '17:00',
            ],
            [
                'name' => 'آرامگاه بوعلی‌سینا',
                'category' => AttractionCategory::Historical,
                'description' => 'بنای یادبود ابن‌سینا، حکیم و پزشک نامدار ایرانی، با معماری برجی مدرن و موزه کوچک همراه.',
                'lat' => 34.7916,
                'lng' => 48.5132,
                'address' => 'میدان بوعلی‌سینا، همدان',
                'avg_visit_minutes' => 60,
                'opens_at' => '08:00',
                'closes_at' => '19:00',
                'boost_weight' => 1,
            ],
            [
                'name' => 'آرامگاه باباطاهر',
                'category' => AttractionCategory::Historical,
                'description' => 'آرامگاه باباطاهر عریان، شاعر و عارف نام‌آشنای همدانی، در باغی آرام در شمال شهر.',
                'lat' => 34.8060,
                'lng' => 48.5155,
                'address' => 'میدان باباطاهر، همدان',
                'avg_visit_minutes' => 30,
                'opens_at' => '08:00',
                'closes_at' => '19:00',
            ],
            [
                'name' => 'تپه باستانی هگمتانه',
                'category' => AttractionCategory::Historical,
                'description' => 'محوطه باستانی پایتخت ماد و هخامنشی باستان - آثار کاوش‌شده و موزه هگمتانه در کنار آن.',
                'lat' => 34.8010,
                'lng' => 48.5200,
                'address' => 'خیابان هگمتانه، همدان',
                'avg_visit_minutes' => 90,
            ],
            [
                'name' => 'میدان امام خمینی (میدان مرکزی همدان)',
                'category' => AttractionCategory::Family,
                'description' => 'میدان ستاره‌ای و تاریخی مرکز شهر همدان، نقطه شروع خوب برای پیاده‌روی به سمت بازار و آرامگاه‌ها.',
                'lat' => 34.79833,
                'lng' => 48.51472,
                'address' => 'میدان امام خمینی، همدان',
                'avg_visit_minutes' => 30,
            ],
            [
                'name' => 'گنبد علویان',
                'category' => AttractionCategory::Historical,
                'description' => 'بنای آجری دوره سلجوقی با گچ‌بری‌های نفیس، یکی از آثار ملی همدان.',
                'lat' => 34.7975,
                'lng' => 48.5090,
                'address' => 'خیابان علویان، همدان',
                'avg_visit_minutes' => 20,
                'opens_at' => '08:00',
                'closes_at' => '17:00',
            ],
            [
                'name' => 'بازار سنتی همدان',
                'category' => AttractionCategory::Shopping,
                'description' => 'بازار تاریخی چندصد ساله با راسته‌های سرپوشیده، مناسب خرید صنایع‌دستی و سوغات.',
                'lat' => 34.7975,
                'lng' => 48.5150,
                'address' => 'بازار همدان، نزدیک میدان امام',
                'avg_visit_minutes' => 90,
            ],
            [
                'name' => 'سنگ شیر همدان',
                'category' => AttractionCategory::Historical,
                'description' => 'مجسمه سنگی شیر از دوره ماد، از نمادهای باستانی شناخته‌شده شهر همدان.',
                'lat' => 34.8020,
                'lng' => 48.5190,
                'address' => 'نزدیک تپه هگمتانه، همدان',
                'avg_visit_minutes' => 15,
            ],
            [
                'name' => 'موزه مردم‌شناسی همدان',
                'category' => AttractionCategory::Historical,
                'description' => 'خانه تاریخی با نمایش اشیا و سبک زندگی سنتی مردم همدان.',
                'lat' => 34.7980,
                'lng' => 48.5140,
                'address' => 'بافت تاریخی مرکز شهر، همدان',
                'avg_visit_minutes' => 45,
                'opens_at' => '09:00',
                'closes_at' => '17:00',
            ],
            [
                'name' => 'پارک مردم همدان',
                'category' => AttractionCategory::Family,
                'description' => 'پارک بزرگ شهری با فضای سبز، مسیر پیاده‌روی و امکانات خانوادگی.',
                'lat' => 34.8050,
                'lng' => 48.5250,
                'address' => 'شمال شرق همدان',
                'avg_visit_minutes' => 60,
            ],
            [
                'name' => 'دریاچه سد اکباتان',
                'category' => AttractionCategory::Nature,
                'description' => 'فضای تفریحی و طبیعی کنار دریاچه سد، مناسب گردش عصرگاهی و عکاسی.',
                'lat' => 34.7000,
                'lng' => 48.4500,
                'address' => 'جاده همدان - تویسرکان',
                'avg_visit_minutes' => 60,
            ],
            [
                'name' => 'برج قوام‌السلطنه',
                'category' => AttractionCategory::Historical,
                'description' => 'عمارت و برج تاریخی متعلق به دوره قاجار، نمونه‌ای از معماری اعیانی آن دوران.',
                'lat' => 34.7975,
                'lng' => 48.5126,
                'address' => 'بافت تاریخی مرکز شهر، همدان',
                'avg_visit_minutes' => 30,
            ],
            [
                'name' => 'روستای هدف گردشگری ورکانه',
                'category' => AttractionCategory::Historical,
                'description' => 'روستای خشتی کوهستانی با معماری پلکانی و منحصربه‌فرد، نمونه‌ای کمیاب از معماری بومی ایران.',
                'lat' => 34.6000,
                'lng' => 48.4300,
                'address' => 'روستای ورکانه، همدان',
                'avg_visit_minutes' => 120,
            ],
        ];

        foreach ($attractions as $index => $attraction) {
            Attraction::firstOrCreate(
                ['name' => $attraction['name']],
                [...$attraction, 'slug' => Str::slug($attraction['name']), 'sort_order' => $index, 'is_active' => true],
            );
        }
    }
}
