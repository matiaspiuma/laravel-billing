<?php

namespace Bhhaskin\Billing\Http\Controllers;

use Bhhaskin\Billing\Events\SubscriptionCanceled;
use Bhhaskin\Billing\Events\SubscriptionCreated;
use Bhhaskin\Billing\Events\SubscriptionResumed;
use Bhhaskin\Billing\Models\Plan;
use Bhhaskin\Billing\Models\Subscription;
use Bhhaskin\Billing\Support\BillingAudit;
use Bhhaskin\Billing\Support\StripeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class SubscriptionController extends Controller
{
    public function __construct(
        protected StripeService $stripeService
    ) {
    }

    /**
     * List user's subscriptions.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $subscriptions = $user->subscriptions()
            ->with('items.plan')
            ->latest()
            ->get();

        return response()->json([
            'data' => $subscriptions,
        ]);
    }

    /**
     * Get a specific subscription.
     */
    public function show(Request $request, string $uuid): JsonResponse
    {
        $subscription = Subscription::where('uuid', $uuid)
            ->with('items.plan')
            ->firstOrFail();

        $this->authorize('view', $subscription);

        return response()->json([
            'data' => $subscription,
        ]);
    }

    /**
     * Create a new subscription.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'plan_uuid' => 'required|string|exists:' . config('billing.tables.plans', 'billing_plans') . ',uuid',
            'quantity' => 'integer|min:1',
        ]);

        $user = $request->user();
        $plan = Plan::where('uuid', $validated['plan_uuid'])->firstOrFail();

        // Ensure user has a customer record
        $customer = $user->getOrCreateCustomer();

        // Create subscription
        $subscription = $customer->subscriptions()->create([
            'status' => Subscription::STATUS_ACTIVE,
            'current_period_start' => now(),
            'current_period_end' => $plan->interval === 'yearly' ? now()->addYear() : now()->addMonth(),
        ]);

        $subscription->addItem($plan, $validated['quantity'] ?? 1);

        // Sync to Stripe if configured
        if (config('billing.stripe.secret')) {
            try {
                $this->stripeService->createSubscription($subscription);
            } catch (\Exception $e) {
                // Handle Stripe error
                $subscription->update(['status' => Subscription::STATUS_INCOMPLETE]);
            }
        }

        BillingAudit::recordSubscriptionChange($subscription, 'created');
        event(new SubscriptionCreated($subscription));

        return response()->json([
            'data' => $subscription->load('items.plan'),
        ], 201);
    }

    /**
     * Cancel a subscription.
     */
    public function destroy(Request $request, string $uuid): JsonResponse
    {
        $validated = $request->validate([
            'immediately' => 'sometimes|boolean',
        ]);

        $subscription = Subscription::where('uuid', $uuid)->firstOrFail();

        $this->authorize('delete', $subscription);

        $immediately = $request->boolean('immediately', false);

        // Get cancellation behavior from the primary plan
        $cancellationBehavior = 'end_of_period';
        foreach ($subscription->items as $item) {
            if ($item->plan->isPlan()) {
                $cancellationBehavior = $item->plan->cancellation_behavior;
                break;
            }
        }

        $cancelImmediately = $immediately || $cancellationBehavior === 'immediate';

        if ($cancelImmediately) {
            $subscription->update([
                'status' => Subscription::STATUS_CANCELED,
                'canceled_at' => now(),
                'ends_at' => now(),
            ]);
        } else {
            $subscription->update([
                'status' => Subscription::STATUS_CANCELED,
                'canceled_at' => now(),
                'ends_at' => $subscription->current_period_end,
            ]);
        }

        // Sync to Stripe if configured
        if (config('billing.stripe.secret') && $subscription->hasStripeId()) {
            try {
                $this->stripeService->cancelSubscription($subscription, $cancelImmediately);
            } catch (\Exception $e) {
                // Log error but don't fail
            }
        }

        BillingAudit::recordSubscriptionChange($subscription, 'canceled', [
            'immediately' => $cancelImmediately,
        ]);
        event(new SubscriptionCanceled($subscription));

        return response()->json([
            'data' => $subscription,
        ]);
    }

    /**
     * Resume a canceled subscription.
     */
    public function resume(Request $request, string $uuid): JsonResponse
    {
        $subscription = Subscription::where('uuid', $uuid)->firstOrFail();

        $this->authorize('resume', $subscription);

        if (! $subscription->onGracePeriod()) {
            return response()->json([
                'error' => 'Subscription cannot be resumed',
            ], 422);
        }

        $subscription->update([
            'status' => Subscription::STATUS_ACTIVE,
            'canceled_at' => null,
            'ends_at' => null,
        ]);

        BillingAudit::recordSubscriptionChange($subscription, 'resumed');
        event(new SubscriptionResumed($subscription));

        return response()->json([
            'data' => $subscription,
        ]);
    }
}
