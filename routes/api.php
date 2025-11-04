<?php

use Bhhaskin\Billing\Http\Controllers\InvoiceController;
use Bhhaskin\Billing\Http\Controllers\PlanController;
use Bhhaskin\Billing\Http\Controllers\SubscriptionController;
use Bhhaskin\Billing\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Billing API Routes
|--------------------------------------------------------------------------
|
| These routes are not automatically registered. Consumer applications
| should include them in their routes/api.php file:
|
| Route::middleware('auth:sanctum')->group(function () {
|     require __DIR__.'/../vendor/bhhaskin/laravel-billing/routes/api.php';
| });
|
| IMPORTANT: Add the webhook route to your VerifyCsrfToken middleware's
| $except array to exclude it from CSRF verification:
|
| protected $except = [
|     'billing/webhook/stripe',
| ];
|
*/

// Public routes (rate limited to prevent abuse)
Route::prefix('billing')->middleware(['throttle:60,1'])->group(function () {
    // Plans
    Route::get('/plans', [PlanController::class, 'index'])->name('billing.plans.index');
    Route::get('/plans/{uuid}', [PlanController::class, 'show'])->name('billing.plans.show');
});

// Protected routes (require authentication middleware in consumer app)
Route::prefix('billing')->middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
    // Subscriptions (read operations)
    Route::get('/subscriptions', [SubscriptionController::class, 'index'])->name('billing.subscriptions.index');
    Route::get('/subscriptions/{uuid}', [SubscriptionController::class, 'show'])->name('billing.subscriptions.show');

    // Subscriptions (write operations - more restrictive rate limit)
    Route::post('/subscriptions', [SubscriptionController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('billing.subscriptions.store');
    Route::delete('/subscriptions/{uuid}', [SubscriptionController::class, 'destroy'])
        ->name('billing.subscriptions.destroy');
    Route::post('/subscriptions/{uuid}/resume', [SubscriptionController::class, 'resume'])
        ->name('billing.subscriptions.resume');

    // Invoices
    Route::get('/invoices', [InvoiceController::class, 'index'])->name('billing.invoices.index');
    Route::get('/invoices/{uuid}', [InvoiceController::class, 'show'])->name('billing.invoices.show');
});

// Webhook routes (no authentication, signature verified in controller)
// Must be excluded from CSRF verification in consumer app's VerifyCsrfToken middleware
Route::post('/billing/webhook/stripe', [WebhookController::class, 'handle'])
    ->middleware('throttle:100,1')
    ->name('billing.webhook.stripe');
