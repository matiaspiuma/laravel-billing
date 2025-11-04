<?php

namespace Bhhaskin\Billing;

use Bhhaskin\Billing\Console\Commands\ProcessBillingCommand;
use Bhhaskin\Billing\Models\Invoice;
use Bhhaskin\Billing\Models\Subscription;
use Bhhaskin\Billing\Policies\InvoicePolicy;
use Bhhaskin\Billing\Policies\SubscriptionPolicy;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Stripe\Stripe;

class BillingServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/billing.php',
            'billing'
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configurePublishing();
        $this->configureMigrations();
        $this->configureCommands();
        $this->configurePolicies();
        $this->configureStripe();
        $this->configureScheduler();
    }

    /**
     * Configure publishing for the package.
     */
    protected function configurePublishing(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/billing.php' => config_path('billing.php'),
            ], 'billing-config');

            $this->publishes([
                __DIR__ . '/../database/migrations' => database_path('migrations'),
            ], 'billing-migrations');

            $this->publishes([
                __DIR__ . '/../database/seeders' => database_path('seeders/billing'),
            ], 'billing-seeders');
        }
    }

    /**
     * Configure migrations for the package.
     */
    protected function configureMigrations(): void
    {
        if ($this->app->runningInConsole()) {
            $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        }
    }

    /**
     * Configure commands for the package.
     */
    protected function configureCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                ProcessBillingCommand::class,
            ]);
        }
    }

    /**
     * Configure authorization policies.
     */
    protected function configurePolicies(): void
    {
        Gate::policy(Subscription::class, SubscriptionPolicy::class);
        Gate::policy(Invoice::class, InvoicePolicy::class);
    }

    /**
     * Configure Stripe.
     */
    protected function configureStripe(): void
    {
        $secret = config('billing.stripe.secret');
        $key = config('billing.stripe.key');
        $webhookSecret = config('billing.stripe.webhook_secret');

        // Validate API keys if provided
        if ($secret && ! str_starts_with($secret, 'sk_')) {
            throw new \InvalidArgumentException(
                'Invalid Stripe secret key. Must start with "sk_".'
            );
        }

        if ($key && ! str_starts_with($key, 'pk_')) {
            throw new \InvalidArgumentException(
                'Invalid Stripe publishable key. Must start with "pk_".'
            );
        }

        if ($webhookSecret && ! str_starts_with($webhookSecret, 'whsec_')) {
            throw new \InvalidArgumentException(
                'Invalid Stripe webhook secret. Must start with "whsec_".'
            );
        }

        if ($secret) {
            Stripe::setApiKey($secret);

            if ($version = config('billing.stripe.api_version')) {
                Stripe::setApiVersion($version);
            }
        }
    }

    /**
     * Configure the scheduler for automatic billing processing.
     */
    protected function configureScheduler(): void
    {
        if (! config('billing.auto_register_scheduler', true)) {
            return;
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('billing:process')->daily();
        });
    }
}
