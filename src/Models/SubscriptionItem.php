<?php

namespace Bhhaskin\Billing\Models;

use Bhhaskin\Billing\Database\Factories\SubscriptionItemFactory;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property int $subscription_id
 * @property int $plan_id
 * @property string|null $stripe_id
 * @property int $quantity
 * @property Carbon|null $trial_ends_at
 * @property Carbon|null $ends_at
 * @property array|null $metadata
 */
class SubscriptionItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'subscription_id',
        'plan_id',
        'stripe_id',
        'quantity',
        'trial_ends_at',
        'ends_at',
        'metadata',
    ];

    protected $guarded = [
        'id',
        'subscription_id',
        'stripe_id',
    ];

    protected $hidden = [
        'stripe_id',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'trial_ends_at' => 'datetime',
        'ends_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected $attributes = [
        'quantity' => 1,
    ];

    public function getTable(): string
    {
        return config('billing.tables.subscription_items', 'billing_subscription_items');
    }

    protected static function booted(): void
    {
        static::creating(function (self $item) {
            if (empty($item->uuid)) {
                $item->uuid = (string) Str::uuid();
            }
        });
    }

    protected static function newFactory(): SubscriptionItemFactory
    {
        return SubscriptionItemFactory::new();
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function usageRecords(): HasMany
    {
        return $this->hasMany(UsageRecord::class);
    }

    public function hasEnded(): bool
    {
        return $this->ends_at && $this->ends_at->isPast();
    }

    public function isActive(): bool
    {
        return ! $this->hasEnded();
    }
}
