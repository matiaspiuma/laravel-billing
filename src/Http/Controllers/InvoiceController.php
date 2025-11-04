<?php

namespace Bhhaskin\Billing\Http\Controllers;

use Bhhaskin\Billing\Models\Invoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class InvoiceController extends Controller
{
    /**
     * List user's invoices.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $invoices = $user->invoices()
            ->with('items')
            ->latest()
            ->paginate(20);

        return response()->json($invoices);
    }

    /**
     * Get a specific invoice.
     */
    public function show(Request $request, string $uuid): JsonResponse
    {
        $invoice = Invoice::where('uuid', $uuid)
            ->with('items.plan')
            ->firstOrFail();

        $this->authorize('view', $invoice);

        return response()->json([
            'data' => $invoice,
        ]);
    }
}
