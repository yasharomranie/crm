<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * The persistent, bottom-tab-bar navigation of the وب‌اپ مشتریان (خانه،
 * سفارش‌های من، کیف پول، باشگاه) - admin-editable (label/icon/order/
 * visibility, from "منوی پایین اپ مشتریان" in the panel) but with a fixed
 * set of four keys, each hard-wired in code to a real app route and an
 * auth requirement (see ROUTES/AUTH_REQUIRED below). Letting an admin
 * rename, re-icon, reorder or hide a tab is safe; letting them type an
 * arbitrary destination URL is not - a broken or malicious link in the
 * app's own primary navigation would be far worse than in one admin-
 * managed home-screen tile among many, so unlike WebAppSectionItem this
 * model deliberately has no link_url field.
 */
class CustomerAppNavItem extends Model
{
    protected $fillable = ['key', 'label', 'icon', 'sort_order', 'is_visible'];

    protected function casts(): array
    {
        return [
            'is_visible' => 'boolean',
        ];
    }

    /**
     * key => [label, icon, route name, whether it requires a signed-in guest].
     *
     * @var array<string, array{label: string, icon: string, route: string, auth_required: bool}>
     */
    public const DEFAULTS = [
        'home' => ['label' => 'خانه', 'icon' => 'home', 'route' => 'customer-app', 'auth_required' => false],
        'bookings' => ['label' => 'سفارش‌های من', 'icon' => 'bag', 'route' => 'customer-app.bookings', 'auth_required' => true],
        'wallet' => ['label' => 'کیف پول', 'icon' => 'wallet', 'route' => 'customer-app.wallet', 'auth_required' => true],
        'club' => ['label' => 'باشگاه', 'icon' => 'star', 'route' => 'customer-app.wallet', 'auth_required' => true],
    ];

    /**
     * The icon choices offered on "منوی پایین اپ مشتریان" - plain inline
     * SVGs rendered by partials.customer-app-bottom-nav (keyed the same
     * way here and there), not a file upload: a handful of simple vector
     * glyphs suit a four-item tab bar far better than arbitrary images.
     *
     * @var array<string, string>
     */
    public const ICONS = [
        'home' => 'خانه',
        'bag' => 'کیف خرید',
        'wallet' => 'کیف پول',
        'star' => 'ستاره',
        'ticket' => 'بلیط',
        'heart' => 'قلب',
        'user' => 'کاربر',
        'bell' => 'زنگوله',
    ];

    /**
     * Makes sure all four rows exist (first boot / after a fresh migrate),
     * seeded from DEFAULTS - never overwrites an admin's existing edits.
     */
    public static function ensureDefaults(): void
    {
        foreach (self::DEFAULTS as $key => $definition) {
            static::query()->firstOrCreate(
                ['key' => $key],
                [
                    'label' => $definition['label'],
                    'icon' => $definition['icon'],
                    'sort_order' => array_search($key, array_keys(self::DEFAULTS), true),
                    'is_visible' => true,
                ],
            );
        }
    }

    /**
     * Visible items in admin-chosen order, each resolved to a real URL -
     * the route/auth_required a key maps to is always read from DEFAULTS
     * (code), never from the database, so nothing admin-editable can ever
     * point the app's own navigation somewhere broken.
     *
     * @return Collection<int, array{key: string, label: string, icon: string, url: string, active: bool}>
     */
    public static function forDisplay(?Guest $guest, ?string $currentRouteName): Collection
    {
        self::ensureDefaults();

        return static::query()
            ->where('is_visible', true)
            ->orderBy('sort_order')
            ->get()
            ->filter(fn (self $item) => array_key_exists($item->key, self::DEFAULTS))
            ->map(function (self $item) use ($guest, $currentRouteName) {
                $definition = self::DEFAULTS[$item->key];
                $needsLogin = $definition['auth_required'] && ! $guest;

                return [
                    'key' => $item->key,
                    'label' => $item->label,
                    'icon' => $item->icon,
                    'url' => $needsLogin ? route('customer-app.auth.login') : route($definition['route']),
                    'active' => ! $needsLogin && $definition['route'] === $currentRouteName,
                ];
            })
            ->values();
    }
}
