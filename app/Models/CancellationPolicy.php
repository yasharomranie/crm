<?php

namespace App\Models;

use Database\Factories\CancellationPolicyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The terms under which a booking can be cancelled: a step table (cancel at
 * least N days before check-in → pay X% of the stay), the percentage for a
 * cancellation later than every step, and the percentage for a no-show.
 * A booking copies these terms when it is made (see Booking::$cancellation_terms),
 * so changing a policy later never changes the promise made to a guest.
 */
class CancellationPolicy extends Model
{
    /** @use HasFactory<CancellationPolicyFactory> */
    use HasFactory;

    protected $fillable = ['name', 'late_penalty_percent', 'no_show_penalty_percent', 'is_default', 'is_active'];

    protected function casts(): array
    {
        return [
            'late_penalty_percent' => 'integer',
            'no_show_penalty_percent' => 'integer',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Only one policy is the default.
        static::saved(function (self $policy) {
            if ($policy->is_default) {
                static::query()->whereKeyNot($policy->id)->where('is_default', true)->update(['is_default' => false]);
            }
        });
    }

    public function rules(): HasMany
    {
        return $this->hasMany(CancellationPolicyRule::class)->orderByDesc('min_days_before');
    }

    public static function defaultPolicy(): ?self
    {
        return static::query()->where('is_default', true)->where('is_active', true)->first();
    }

    /**
     * The policy as a plain array a booking can keep.
     *
     * @return array{name: string, rules: list<array{days: int, percent: int}>, late_percent: int, no_show_percent: int}
     */
    public function terms(): array
    {
        return [
            'name' => $this->name,
            'rules' => $this->rules()->get()
                ->map(fn (CancellationPolicyRule $rule) => ['days' => $rule->min_days_before, 'percent' => $rule->penalty_percent])
                ->values()
                ->all(),
            'late_percent' => $this->late_penalty_percent,
            'no_show_percent' => $this->no_show_penalty_percent,
        ];
    }

    /**
     * A one-line description of terms, for screens and messages.
     *
     * @param  array{name?: string, rules?: list<array{days: int, percent: int}>, late_percent?: int, no_show_percent?: int}  $terms
     */
    public static function describe(array $terms): string
    {
        $parts = [];

        foreach ($terms['rules'] ?? [] as $rule) {
            $parts[] = $rule['percent'] === 0
                ? "لغو تا {$rule['days']} روز مانده به ورود: رایگان"
                : "لغو تا {$rule['days']} روز مانده به ورود: {$rule['percent']}٪ جریمه";
        }

        $parts[] = 'لغو دیرتر: '.($terms['late_percent'] ?? 0).'٪ جریمه';
        $parts[] = 'عدم حضور: '.($terms['no_show_percent'] ?? 0).'٪ جریمه';

        return implode(' — ', $parts);
    }
}
