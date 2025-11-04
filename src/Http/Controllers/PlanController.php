<?php

namespace Bhhaskin\Billing\Http\Controllers;

use Bhhaskin\Billing\Models\Plan;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class PlanController extends Controller
{
    /**
     * List all active plans.
     */
    public function index(): JsonResponse
    {
        $plans = Plan::active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $plans,
        ]);
    }

    /**
     * Get a specific plan.
     */
    public function show(string $uuid): JsonResponse
    {
        // Only show active plans to prevent exposing internal/inactive plans
        $plan = Plan::where('uuid', $uuid)
            ->active()
            ->firstOrFail();

        return response()->json([
            'data' => $plan,
        ]);
    }
}
