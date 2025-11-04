<?php

namespace Bhhaskin\Billing\Policies;

use Bhhaskin\Billing\Models\Subscription;
use Illuminate\Contracts\Auth\Authenticatable;

class SubscriptionPolicy
{
    /**
     * Determine if the user can view the subscription.
     */
    public function view(Authenticatable $user, Subscription $subscription): bool
    {
        // Check if the subscription belongs to the user's customer
        return $this->userOwnsSubscription($user, $subscription);
    }

    /**
     * Determine if the user can update the subscription.
     */
    public function update(Authenticatable $user, Subscription $subscription): bool
    {
        return $this->userOwnsSubscription($user, $subscription);
    }

    /**
     * Determine if the user can delete/cancel the subscription.
     */
    public function delete(Authenticatable $user, Subscription $subscription): bool
    {
        return $this->userOwnsSubscription($user, $subscription);
    }

    /**
     * Determine if the user can resume the subscription.
     */
    public function resume(Authenticatable $user, Subscription $subscription): bool
    {
        return $this->userOwnsSubscription($user, $subscription);
    }

    /**
     * Check if the user owns the subscription through their customer record.
     */
    protected function userOwnsSubscription(Authenticatable $user, Subscription $subscription): bool
    {
        // Get the user's customer
        $customer = $subscription->customer;

        if (!$customer) {
            return false;
        }

        // Check if the customer's billable model matches the authenticated user
        return $customer->billable_type === get_class($user)
            && $customer->billable_id === $user->getAuthIdentifier();
    }
}
