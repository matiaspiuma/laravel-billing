<?php

namespace Bhhaskin\Billing\Models;

use Bhhaskin\Billing\Database\Factories\SubscriptionFactory;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property int $customer_id
 * @property int|null $workspace_id
 * @property string|null $stripe_id
 * @property string|null $stripe_status
 * @property string $status
 * @property Carbon|null $trial_ends_at
 * @property Carbon|null $current_period_start
 * @property Carbon|null $current_period_end
 * @property Carbon|null $canceled_at
 * @property Carbon|null $ends_at
 * @property int $failed_payment_count
 * @property Carbon|null $last_failed_payment_at
 * @property array|null $metadata
 */
class Subscription extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_TRIALING = 'trialing';
    public const STATUS_PAST_DUE = 'past_due';
    public const STATUS_CANCELED = 'canceled';
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_INCOMPLETE = 'incomplete';

    protected $fillable = [
        'uuid',
        'customer_id',
        'workspace_id',
        'stripe_id',
        'stripe_status',
        'status',
        'trial_ends_at',
        'current_period_start',
        'current_period_end',
        'canceled_at',
        'ends_at',
        'failed_payment_count',
        'last_failed_payment_at',
        'metadata',
    ];

    protected $guarded = [
        'id',
        'customer_id',
        'stripe_id',
        'stripe_status',
    ];

    protected $hidden = [
        'stripe_id',
        'stripe_status',
    ];

    protected $casts = [
        'trial_ends_at' => 'datetime',
        'current_period_start' => 'datetime',
        'current_period_end' => 'datetime',
        'canceled_at' => 'datetime',
        'ends_at' => 'datetime',
        'last_failed_payment_at' => 'datetime',
        'failed_payment_count' => 'integer',
        'metadata' => 'array',
    ];

    protected $attributes = [
        'status' => self::STATUS_ACTIVE,
        'failed_payment_count' => 0,
    ];

    public function getTable(): string
    {
        return config('billing.tables.subscriptions', 'billing_subscriptions');
    }

    protected static function booted(): void
    {
        static::creating(function (self $subscription) {
            if (empty($subscription->uuid)) {
                $subscription->uuid = (string) Str::uuid();
            }
        });
    }

    protected static function newFactory(): SubscriptionFactory
    {
        return SubscriptionFactory::new();
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function workspace(): BelongsTo
    {
        if (! config('billing.workspace_model')) {
            throw new \RuntimeException('Workspace model is not configured');
        }

        return $this->belongsTo(config('billing.workspace_model'));
    }

    public function items(): HasMany
    {
        return $this->hasMany(SubscriptionItem::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeTrialing(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_TRIALING);
    }

    public function scopePastDue(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PAST_DUE);
    }

    public function scopeCanceled(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_CANCELED);
    }

    public function scopeEnded(Builder $query): Builder
    {
        return $query->whereNotNull('ends_at')->where('ends_at', '<=', now());
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE && ! $this->hasEnded();
    }

    public function isTrialing(): bool
    {
        return $this->status === self::STATUS_TRIALING
            && $this->trial_ends_at
            && $this->trial_ends_at->isFuture();
    }

    public function isPastDue(): bool
    {
        return $this->status === self::STATUS_PAST_DUE;
    }

    public function isCanceled(): bool
    {
        return $this->status === self::STATUS_CANCELED;
    }

    public function isSuspended(): bool
    {
        return $this->status === self::STATUS_SUSPENDED;
    }

    public function hasEnded(): bool
    {
        return $this->ends_at && $this->ends_at->isPast();
    }

    public function onGracePeriod(): bool
    {
        return $this->canceled_at && ! $this->hasEnded();
    }

    public function hasStripeId(): bool
    {
        return ! empty($this->stripe_id);
    }

    public function addItem(Plan $plan, int $quantity = 1): SubscriptionItem
    {
        // Validate that addon doesn't require a plan if this subscription has no plans
        if ($plan->requiresPlan() && ! $this->hasPlans()) {
            throw new \InvalidArgumentException('This addon requires a base plan');
        }

        return $this->items()->create([
            'plan_id' => $plan->id,
            'quantity' => $quantity,
        ]);
    }

    public function hasPlans(): bool
    {
        return $this->items()->whereHas('plan', function (Builder $query) {
            $query->where('type', Plan::TYPE_PLAN);
        })->exists();
    }

    public function hasPlan(Plan $plan): bool
    {
        return $this->items()->where('plan_id', $plan->id)->exists();
    }

    public function getItem(Plan $plan): ?SubscriptionItem
    {
        return $this->items()->where('plan_id', $plan->id)->first();
    }
}
