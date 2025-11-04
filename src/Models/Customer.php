<?php

namespace Bhhaskin\Billing\Models;

use Bhhaskin\Billing\Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property string $billable_type
 * @property int $billable_id
 * @property int|null $workspace_id
 * @property string|null $stripe_id
 * @property string $email
 * @property string|null $name
 * @property array|null $metadata
 */
class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'billable_type',
        'billable_id',
        'workspace_id',
        'stripe_id',
        'email',
        'name',
        'metadata',
    ];

    protected $guarded = [
        'id',
        'stripe_id',
    ];

    protected $hidden = [
        'stripe_id',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function getTable(): string
    {
        return config('billing.tables.customers', 'billing_customers');
    }

    protected static function booted(): void
    {
        static::creating(function (self $customer) {
            if (empty($customer->uuid)) {
                $customer->uuid = (string) Str::uuid();
            }
        });
    }

    protected static function newFactory(): CustomerFactory
    {
        return CustomerFactory::new();
    }

    public function billable(): MorphTo
    {
        return $this->morphTo();
    }

    public function workspace(): BelongsTo
    {
        if (! config('billing.workspace_model')) {
            throw new \RuntimeException('Workspace model is not configured');
        }

        return $this->belongsTo(config('billing.workspace_model'));
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function paymentMethods(): HasMany
    {
        return $this->hasMany(PaymentMethod::class);
    }

    public function defaultPaymentMethod(): ?PaymentMethod
    {
        return $this->paymentMethods()->where('is_default', true)->first();
    }

    public function hasStripeId(): bool
    {
        return ! empty($this->stripe_id);
    }

    public function activeSubscriptions(): HasMany
    {
        return $this->subscriptions()->whereIn('status', ['active', 'trialing']);
    }

    public function hasActiveSubscription(): bool
    {
        return $this->activeSubscriptions()->exists();
    }
}
