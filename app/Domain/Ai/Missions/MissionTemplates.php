<?php

namespace App\Domain\Ai\Missions;

/**
 * Ready-made missions for the questions a hotel manager asks again and again.
 * A template only fills in the form (goal, directives, the lookups each
 * specialist will need and a suggested schedule); the manager still picks which
 * assistant plays each part and can change everything before creating it.
 *
 * `needs` are CrmToolCatalog tool names: the picker favours assistants that have
 * been granted them, and warns when a chosen one lacks them (its findings would
 * then have no source).
 */
class MissionTemplates
{
    /**
     * @return array<string, array{icon: string, title: string, about: string, goal: string, end: string, coordinator: string, specialists: list<array{directive: string, needs: list<string>}>, critic: ?string, schedule: array{frequency: string, time: string, weekday: ?int, day: ?int, label: string}}>
     */
    public function all(): array
    {
        return [
            'week-ahead' => [
                'icon' => '📅',
                'title' => 'وضعیت رزرو و پشتیبانی هفتهٔ آینده',
                'about' => 'کجا اتاق خالی کم است، چه مهمانی پیگیری می‌خواهد و کدام تیکت باز مانده.',
                'goal' => 'برای هفتهٔ آینده بگو کدام روزها اتاق خالی کم است، کدام مهمان یا رزرو نیاز به پیگیری دارد و کدام تیکت پشتیبانی باز مانده است.',
                'end' => 'وقتی برای هر سه بخش (اتاق، مهمان، تیکت) یافتهٔ منبع‌دار داریم.',
                'coordinator' => 'یافته‌ها را جمع کن و فقط تصمیم‌هایی پیشنهاد بده که شاهد دارند.',
                'specialists' => [
                    ['directive' => 'با ابزار پیش‌بینی اشغال و اتاق‌های خالی، وضعیت هفتهٔ آینده را بررسی کن.', 'needs' => ['get_occupancy_forecast', 'check_room_availability']],
                    ['directive' => 'با ابزار وضعیت تیکت‌های پشتیبانی و مشتریان، چیزهایی را که پیگیری می‌خواهند پیدا کن.', 'needs' => ['check_support_ticket_status', 'lookup_guest']],
                ],
                'critic' => 'هر ادعای بی‌منبع یا عددی که با ابزار تأیید نشده را به چالش بکش.',
                'schedule' => ['frequency' => 'weekly', 'time' => '08:00', 'weekday' => 6, 'day' => null, 'label' => 'هر شنبه ساعت ۰۸:۰۰'],
            ],
            'morning-brief' => [
                'icon' => '🌅',
                'title' => 'گزارش صبحگاهی مدیریت',
                'about' => 'امروز چه خبر است: ورودی‌ها، خروجی‌ها، اتاق‌های خارج از سرویس، تیکت‌های باز.',
                'goal' => 'یک گزارش کوتاه برای شروع روز بنویس: ورودی‌ها و خروجی‌های امروز، تأخیر خروج، اتاق‌های خارج از سرویس، یادداشت‌های باز تحویل شیفت، دریافتی دیروز و تیکت‌های پشتیبانی که باید زود پاسخ داده شوند.',
                'end' => 'وقتی هر بخش گزارش، عددی با منبع دارد.',
                'coordinator' => 'گزارش را حداکثر در چند خط و به‌ترتیب اولویت بنویس؛ فقط مواردی را پیشنهاد کن که امروز نیاز به اقدام دارند.',
                'specialists' => [
                    ['directive' => 'با ابزار وضعیت عملیاتی امروز، وضعیت اقامتگاه را گزارش کن.', 'needs' => ['get_today_operations']],
                    ['directive' => 'تیکت‌های پشتیبانی باز و نزدیک به مهلت را پیدا کن.', 'needs' => ['check_support_ticket_status']],
                ],
                'critic' => null,
                'schedule' => ['frequency' => 'daily', 'time' => '07:30', 'weekday' => null, 'day' => null, 'label' => 'هر روز ساعت ۰۷:۳۰'],
            ],
            'weekly-performance' => [
                'icon' => '📈',
                'title' => 'عملکرد هفتهٔ اقامتگاه',
                'about' => 'اشغال، ADR، RevPAR، لغوها و کانال‌های برتر هفتهٔ گذشته، به‌علاوهٔ نگاه به هفتهٔ پیش‌رو.',
                'goal' => 'عملکرد هفتهٔ گذشته را (اشغال، ADR، RevPAR، درآمد، لغو و عدم حضور، کانال‌های فروش برتر) بگو و با پیش‌بینی هفتهٔ پیش‌رو مقایسه کن؛ هر چیزی که نگران‌کننده یا فرصت است را مشخص کن.',
                'end' => 'وقتی اعداد هفتهٔ گذشته و پیش‌بینی هفتهٔ آینده کنار هم گذاشته شده‌اند.',
                'coordinator' => 'اعداد را با هم مقایسه کن و حداکثر سه پیشنهاد عملی بده؛ هر پیشنهاد باید به یک عدد تکیه کند.',
                'specialists' => [
                    ['directive' => 'گزارش عملکرد هفتهٔ گذشته را با ابزار بگیر و اعداد کلیدی را بیاور.', 'needs' => ['get_hotel_report']],
                    ['directive' => 'پیش‌بینی اشغال هفتهٔ آینده را با ابزار بگیر و روزهای کم‌اشغال و پراشغال را مشخص کن.', 'needs' => ['get_occupancy_forecast']],
                ],
                'critic' => 'هر نتیجه‌گیری‌ای را که با عدد ابزار پشتیبانی نمی‌شود یا بازهٔ مقایسه‌اش نامعلوم است به چالش بکش.',
                'schedule' => ['frequency' => 'weekly', 'time' => '09:00', 'weekday' => 6, 'day' => null, 'label' => 'هر شنبه ساعت ۰۹:۰۰'],
            ],
            'month-close' => [
                'icon' => '🗓',
                'title' => 'جمع‌بندی ماه',
                'about' => 'اشغال، درآمد، لغوها و کانال‌های فروش ۳۰ روز گذشته و تصمیم‌های ماه بعد.',
                'goal' => 'عملکرد ۳۰ روز گذشته را جمع‌بندی کن: اشغال، ADR، RevPAR، درآمد، نرخ لغو و عدم حضور و سهم کانال‌های فروش؛ بعد بگو برای ماه بعد چه چیزهایی را باید عوض کنیم.',
                'end' => 'وقتی همهٔ اعداد ماه با منبع آمده و برای ماه بعد پیشنهاد مشخص داریم.',
                'coordinator' => 'یک جمع‌بندی ماهانه بنویس و برای ماه بعد حداکثر پنج تصمیم پیشنهادی بده؛ هرکدام با دلیلش.',
                'specialists' => [
                    ['directive' => 'گزارش عملکرد ۳۰ روز گذشته را با ابزار بگیر و اعداد کلیدی و سهم کانال‌ها را بیاور.', 'needs' => ['get_hotel_report']],
                    ['directive' => 'پیش‌بینی اشغال روزهای آینده را بگیر و دربارهٔ ماه پیش‌رو بگو.', 'needs' => ['get_occupancy_forecast']],
                ],
                'critic' => 'عددهای بی‌منبع، مقایسه‌های بی‌پایه و تصمیم‌هایی را که به عدد تکیه ندارند به چالش بکش.',
                'schedule' => ['frequency' => 'monthly', 'time' => '09:00', 'weekday' => null, 'day' => 1, 'label' => 'روز ۱ هر ماه ساعت ۰۹:۰۰'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $key): ?array
    {
        return $this->all()[$key] ?? null;
    }
}
