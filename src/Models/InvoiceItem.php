<?php

namespace Bhhaskin\Billing\Models;

use Bhhaskin\Billing\Database\Factories\InvoiceItemFactory;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property int $invoice_id
 * @property int|null $subscription_id
 * @property int|null $plan_id
 * @property string $description
 * @property int $quantity
 * @property float $unit_price
 * @property float $amount
 * @property bool $is_proration
 * @property Carbon|null $period_start
 * @property Carbon|null $period_end
 * @property array|null $metadata
 */
class InvoiceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'invoice_id',
        'subscription_id',
        'plan_id',
        'description',
        'quantity',
        'unit_price',
        'amount',
        'is_proration',
        'period_start',
        'period_end',
        'metadata',
    ];

    protected $guarded = [
        'id',
        'invoice_id',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'amount' => 'decimal:2',
        'is_proration' => 'boolean',
        'period_start' => 'datetime',
        'period_end' => 'datetime',
        'metadata' => 'array',
    ];

    protected $attributes = [
        'quantity' => 1,
        'is_proration' => false,
    ];

    public function getTable(): string
    {
        return config('billing.tables.invoice_items', 'billing_invoice_items');
    }

    protected static function booted(): void
    {
        static::creating(function (self $item) {
            if (empty($item->uuid)) {
                $item->uuid = (string) Str::uuid();
            }

            // Calculate amount if not set
            if (! isset($item->amount)) {
                $item->amount = $item->quantity * $item->unit_price;
            }
        });

        static::saved(function (self $item) {
            // Recalculate invoice totals
            $item->invoice->calculateTotals();
        });

        static::deleted(function (self $item) {
            // Recalculate invoice totals
            $item->invoice->calculateTotals();
        });
    }

    protected static function newFactory(): InvoiceItemFactory
    {
        return InvoiceItemFactory::new();
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }
}
