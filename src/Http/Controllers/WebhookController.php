<?php

namespace Bhhaskin\Billing\Http\Controllers;

use Bhhaskin\Billing\Events\PaymentFailed;
use Bhhaskin\Billing\Events\PaymentSucceeded;
use Bhhaskin\Billing\Models\Invoice;
use Bhhaskin\Billing\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;

class WebhookController extends Controller
{
    /**
     * Handle incoming Stripe webhook.
     */
    public function handle(Request $request)
    {
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature');
        $secret = config('billing.stripe.webhook_secret');

        if (! $secret) {
            Log::error('Stripe webhook received but webhook secret not configured');
            return response()->json(['error' => 'Webhook secret not configured'], 500);
        }

        try {
            $event = Webhook::constructEvent($payload, $signature, $secret);
        } catch (SignatureVerificationException $e) {
            // Log signature verification failures for security monitoring
            Log::warning('Stripe webhook signature verification failed', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'signature' => substr($signature ?? '', 0, 20) . '...',
                'error' => $e->getMessage(),
            ]);
            return response()->json(['error' => 'Invalid signature'], 400);
        } catch (\UnexpectedValueException $e) {
            // JSON parsing error
            Log::error('Stripe webhook JSON parsing error', [
                'ip' => $request->ip(),
                'error' => $e->getMessage(),
            ]);
            return response()->json(['error' => 'Invalid payload'], 400);
        } catch (\Exception $e) {
            // Other errors during event construction
            Log::error('Stripe webhook processing error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['error' => 'Webhook processing failed'], 400);
        }

        // Log received webhook for debugging
        Log::info('Stripe webhook received', [
            'event_type' => $event->type,
            'event_id' => $event->id,
        ]);

        // Handle the event with try-catch to prevent one failure from breaking webhook processing
        try {
            switch ($event->type) {
                case 'invoice.payment_succeeded':
                    $this->handleInvoicePaymentSucceeded($event->data->object);
                    break;

                case 'invoice.payment_failed':
                    $this->handleInvoicePaymentFailed($event->data->object);
                    break;

                case 'customer.subscription.updated':
                    $this->handleSubscriptionUpdated($event->data->object);
                    break;

                case 'customer.subscription.deleted':
                    $this->handleSubscriptionDeleted($event->data->object);
                    break;

                case 'payment_method.attached':
                    $this->handlePaymentMethodAttached($event->data->object);
                    break;

                default:
                    // Log unhandled event types for monitoring
                    Log::info('Unhandled Stripe webhook event type', [
                        'event_type' => $event->type,
                        'event_id' => $event->id,
                    ]);
                    break;
            }
        } catch (\Exception $e) {
            Log::error('Error handling Stripe webhook event', [
                'event_type' => $event->type,
                'event_id' => $event->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            // Still return 200 to prevent Stripe from retrying
            // The error is logged for manual investigation
        }

        return response()->json(['status' => 'success']);
    }

    /**
     * Handle successful invoice payment.
     */
    protected function handleInvoicePaymentSucceeded($stripeInvoice): void
    {
        $invoice = Invoice::where('stripe_id', $stripeInvoice->id)->first();

        if ($invoice) {
            $invoice->markAsPaid();
            event(new PaymentSucceeded($invoice));
        }
    }

    /**
     * Handle failed invoice payment.
     */
    protected function handleInvoicePaymentFailed($stripeInvoice): void
    {
        $subscriptionId = $stripeInvoice->subscription ?? null;

        if ($subscriptionId) {
            $subscription = Subscription::where('stripe_id', $subscriptionId)->first();

            if ($subscription) {
                $subscription->increment('failed_payment_count');
                $subscription->update([
                    'status' => Subscription::STATUS_PAST_DUE,
                    'last_failed_payment_at' => now(),
                ]);

                event(new PaymentFailed($subscription, $stripeInvoice->last_finalization_error->message ?? null));
            }
        }
    }

    /**
     * Handle subscription update.
     */
    protected function handleSubscriptionUpdated($stripeSubscription): void
    {
        $subscription = Subscription::where('stripe_id', $stripeSubscription->id)->first();

        if ($subscription) {
            $subscription->update([
                'stripe_status' => $stripeSubscription->status,
                'current_period_start' => $stripeSubscription->current_period_start
                    ? date('Y-m-d H:i:s', $stripeSubscription->current_period_start)
                    : null,
                'current_period_end' => $stripeSubscription->current_period_end
                    ? date('Y-m-d H:i:s', $stripeSubscription->current_period_end)
                    : null,
            ]);
        }
    }

    /**
     * Handle subscription deletion.
     */
    protected function handleSubscriptionDeleted($stripeSubscription): void
    {
        $subscription = Subscription::where('stripe_id', $stripeSubscription->id)->first();

        if ($subscription) {
            $subscription->update([
                'status' => Subscription::STATUS_CANCELED,
                'ends_at' => now(),
            ]);
        }
    }

    /**
     * Handle payment method attachment.
     */
    protected function handlePaymentMethodAttached($stripePaymentMethod): void
    {
        // This can be used to sync payment methods to the database
        // Implementation depends on requirements
    }
}
