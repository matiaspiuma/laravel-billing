<?php

namespace Bhhaskin\Billing\Models;

use Bhhaskin\Billing\Database\Factories\PlanFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string|null $stripe_product_id
 * @property string|null $stripe_price_id
 * @property float $price
 * @property string $interval
 * @property int $interval_count
 * @property string $type
 * @property bool $requires_plan
 * @property bool $is_active
 * @property int $trial_period_days
 * @property int $grace_period_days
 * @property string $cancellation_behavior
 * @property string $change_behavior
 * @property bool $prorate_changes
 * @property bool $prorate_cancellations
 * @property array|null $features
 * @property array|null $limits
 * @property array|null $metadata
 * @property int $sort_order
 */
class Plan extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const TYPE_PLAN = 'plan';
    public const TYPE_ADDON = 'addon';

    public const INTERVAL_MONTHLY = 'monthly';
    public const INTERVAL_YEARLY = 'yearly';

    public const CANCELLATION_IMMEDIATE = 'immediate';
    public const CANCELLATION_END_OF_PERIOD = 'end_of_period';

    public const CHANGE_IMMEDIATE = 'immediate';
    public const CHANGE_END_OF_PERIOD = 'end_of_period';

    protected $fillable = [
        'uuid',
        'name',
        'slug',
        'description',
        'stripe_product_id',
        'stripe_price_id',
        'price',
        'interval',
        'interval_count',
        'type',
        'requires_plan',
        'is_active',
        'trial_period_days',
        'grace_period_days',
        'cancellation_behavior',
        'change_behavior',
        'prorate_changes',
        'prorate_cancellations',
        'features',
        'limits',
        'metadata',
        'sort_order',
    ];

    protected $guarded = [
        'id',
        'stripe_product_id',
        'stripe_price_id',
    ];

    protected $hidden = [
        'stripe_product_id',
        'stripe_price_id',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'requires_plan' => 'boolean',
        'is_active' => 'boolean',
        'trial_period_days' => 'integer',
        'grace_period_days' => 'integer',
        'prorate_changes' => 'boolean',
        'prorate_cancellations' => 'boolean',
        'features' => 'array',
        'limits' => 'array',
        'metadata' => 'array',
        'sort_order' => 'integer',
        'interval_count' => 'integer',
    ];

    protected $attributes = [
        'type' => self::TYPE_PLAN,
        'requires_plan' => false,
        'is_active' => true,
        'interval_count' => 1,
        'sort_order' => 0,
    ];

    public function getTable(): string
    {
        return config('billing.tables.plans', 'billing_plans');
    }

    protected static function booted(): void
    {
        static::creating(function (self $plan) {
            if (empty($plan->uuid)) {
                $plan->uuid = (string) Str::uuid();
            }

            // Apply defaults from config if not set
            $defaults = config('billing.plan_defaults', []);

            foreach ($defaults as $key => $value) {
                $snakeKey = Str::snake($key);
                if (! isset($plan->attributes[$snakeKey])) {
                    $plan->{$snakeKey} = $value;
                }
            }
        });
    }

    protected static function newFactory(): PlanFactory
    {
        return PlanFactory::new();
    }

    public function subscriptionItems(): HasMany
    {
        return $this->hasMany(SubscriptionItem::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopePlans(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_PLAN);
    }

    public function scopeAddons(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_ADDON);
    }

    public function scopeStandalone(Builder $query): Builder
    {
        return $query->where('requires_plan', false);
    }

    public function scopeMonthly(Builder $query): Builder
    {
        return $query->where('interval', self::INTERVAL_MONTHLY);
    }

    public function scopeYearly(Builder $query): Builder
    {
        return $query->where('interval', self::INTERVAL_YEARLY);
    }

    public function isPlan(): bool
    {
        return $this->type === self::TYPE_PLAN;
    }

    public function isAddon(): bool
    {
        return $this->type === self::TYPE_ADDON;
    }

    public function requiresPlan(): bool
    {
        return $this->requires_plan;
    }

    public function isStandalone(): bool
    {
        return ! $this->requires_plan;
    }

    public function hasLimit(string $key): bool
    {
        return isset($this->limits[$key]);
    }

    public function getLimit(string $key, $default = null)
    {
        return $this->limits[$key] ?? $default;
    }

    public function hasFeature(string $feature): bool
    {
        return in_array($feature, $this->features ?? [], true);
    }

    public function getFormattedPrice(): string
    {
        $currency = strtoupper(config('billing.currency', 'usd'));
        $symbol = $currency === 'USD' ? '$' : $currency . ' ';

        return $symbol . number_format($this->price, 2);
    }

    public function getIntervalLabel(): string
    {
        $label = $this->interval;

        if ($this->interval_count > 1) {
            $label = $this->interval_count . ' ' . Str::plural($this->interval);
        }

        return $label;
    }
}
