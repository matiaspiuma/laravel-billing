<?php

namespace Bhhaskin\Billing\Models;

use Bhhaskin\Billing\Database\Factories\UsageRecordFactory;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property int $subscription_item_id
 * @property int $quantity
 * @property string $action
 * @property Carbon $timestamp
 * @property bool $reported_to_stripe
 * @property array|null $metadata
 */
class UsageRecord extends Model
{
    use HasFactory;

    public const ACTION_SET = 'set';
    public const ACTION_INCREMENT = 'increment';

    protected $fillable = [
        'uuid',
        'subscription_item_id',
        'quantity',
        'action',
        'timestamp',
        'reported_to_stripe',
        'metadata',
    ];

    protected $guarded = [
        'id',
        'subscription_item_id',
        'reported_to_stripe',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'timestamp' => 'datetime',
        'reported_to_stripe' => 'boolean',
        'metadata' => 'array',
    ];

    protected $attributes = [
        'action' => self::ACTION_SET,
        'reported_to_stripe' => false,
    ];

    public function getTable(): string
    {
        return config('billing.tables.usage_records', 'billing_usage_records');
    }

    protected static function booted(): void
    {
        static::creating(function (self $record) {
            if (empty($record->uuid)) {
                $record->uuid = (string) Str::uuid();
            }

            if (empty($record->timestamp)) {
                $record->timestamp = now();
            }
        });
    }

    protected static function newFactory(): UsageRecordFactory
    {
        return UsageRecordFactory::new();
    }

    public function subscriptionItem(): BelongsTo
    {
        return $this->belongsTo(SubscriptionItem::class);
    }

    public function isReported(): bool
    {
        return $this->reported_to_stripe;
    }

    public function markAsReported(): void
    {
        $this->update(['reported_to_stripe' => true]);
    }
}
