<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A sellable attraction/service (cable car, aquarium cave, sled, ...).
 * Deliberately generic - never referenced by name in code - so an admin
 * can add a whole new attraction from the panel with zero deploys.
 */
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll()->logOnlyDirty()->useLogName('product');
    }

    protected $fillable = [
        'name', 'slug', 'code', 'description', 'image',
        'is_active', 'sort_order', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $product) {
            $product->slug ??= Str::slug($product->name);
        });
    }

    public function ticketVariants(): HasMany
    {
        return $this->hasMany(TicketVariant::class);
    }

    public function gates(): HasMany
    {
        return $this->hasMany(Gate::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
