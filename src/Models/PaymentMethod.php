<?php

namespace Bhhaskin\Billing\Models;

use Bhhaskin\Billing\Database\Factories\PaymentMethodFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property int $customer_id
 * @property string $stripe_id
 * @property string $type
 * @property string|null $brand
 * @property string|null $last_four
 * @property string|null $exp_month
 * @property string|null $exp_year
 * @property bool $is_default
 * @property array|null $metadata
 */
class PaymentMethod extends Model
{
    use HasFactory;

    public const TYPE_CARD = 'card';
    public const TYPE_BANK_ACCOUNT = 'bank_account';

    protected $fillable = [
        'uuid',
        'customer_id',
        'stripe_id',
        'type',
        'brand',
        'last_four',
        'exp_month',
        'exp_year',
        'is_default',
        'metadata',
    ];

    protected $guarded = [
        'id',
        'customer_id',
        'stripe_id',
    ];

    protected $hidden = [
        'stripe_id',
        'exp_month',
        'exp_year',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'metadata' => 'array',
    ];

    protected $attributes = [
        'is_default' => false,
    ];

    public function getTable(): string
    {
        return config('billing.tables.payment_methods', 'billing_payment_methods');
    }

    protected static function booted(): void
    {
        static::creating(function (self $paymentMethod) {
            if (empty($paymentMethod->uuid)) {
                $paymentMethod->uuid = (string) Str::uuid();
            }
        });

        // Ensure only one default payment method per customer
        static::saving(function (self $paymentMethod) {
            if ($paymentMethod->is_default && $paymentMethod->isDirty('is_default')) {
                static::where('customer_id', $paymentMethod->customer_id)
                    ->where('id', '!=', $paymentMethod->id)
                    ->update(['is_default' => false]);
            }
        });
    }

    protected static function newFactory(): PaymentMethodFactory
    {
        return PaymentMethodFactory::new();
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function isCard(): bool
    {
        return $this->type === self::TYPE_CARD;
    }

    public function isBankAccount(): bool
    {
        return $this->type === self::TYPE_BANK_ACCOUNT;
    }

    public function isExpired(): bool
    {
        if (! $this->exp_month || ! $this->exp_year) {
            return false;
        }

        $expirationDate = now()->setYear((int) $this->exp_year)
            ->setMonth((int) $this->exp_month)
            ->endOfMonth();

        return $expirationDate->isPast();
    }

    public function getDisplayName(): string
    {
        if ($this->isCard() && $this->brand && $this->last_four) {
            return ucfirst($this->brand) . ' ****' . $this->last_four;
        }

        if ($this->isBankAccount() && $this->last_four) {
            return 'Bank Account ****' . $this->last_four;
        }

        return ucfirst($this->type);
    }
}
