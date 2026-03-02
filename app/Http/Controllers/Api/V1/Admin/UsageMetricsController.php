<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UsageMetricsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $period = $request->get('period', '30'); // days

        $metrics = cache()->remember("admin.metrics.{$period}", now()->addMinutes(10), function () use ($period) {
            $from = now()->subDays((int) $period);

            return [
                'properties' => [
                    'total'      => Property::count(),
                    'active'     => Property::where('is_active', true)->count(),
                    'new'        => Property::where('created_at', '>=', $from)->count(),
                    'trial'      => Property::where('subscription_status', 'trial')->count(),
                    'paid'       => Property::where('subscription_status', 'active')->count(),
                    'suspended'  => Property::where('subscription_status', 'suspended')->count(),
                ],
                'users' => [
                    'total'  => User::count(),
                    'active' => User::where('is_active', true)->count(),
                    'new'    => User::where('created_at', '>=', $from)->count(),
                ],
                'reservations' => [
                    'total'     => Reservation::count(),
                    'new'       => Reservation::where('created_at', '>=', $from)->count(),
                    'confirmed' => Reservation::where('status', 'confirmed')->count(),
                    'checked_in'=> Reservation::where('status', 'checked_in')->count(),
                ],
            ];
        });

        return response()->json([
            'success' => true,
            'data'    => $metrics,
        ]);
    }

    public function revenue(Request $request): JsonResponse
    {
        $months = (int) $request->get('months', 12);

        $revenue = DB::table('reservations')
            ->where('status', 'checked_out')
            ->whereRaw("created_at >= now() - interval '{$months} months'")
            ->selectRaw("DATE_TRUNC('month', check_out_date) as month, SUM(total_amount) as revenue, COUNT(*) as bookings")
            ->groupByRaw("DATE_TRUNC('month', check_out_date)")
            ->orderBy('month')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $revenue,
        ]);
    }

    public function growth(Request $request): JsonResponse
    {
        $months = (int) $request->get('months', 12);

        $growth = DB::table('properties')
            ->whereRaw("created_at >= now() - interval '{$months} months'")
            ->selectRaw("DATE_TRUNC('month', created_at) as month, COUNT(*) as new_properties")
            ->groupByRaw("DATE_TRUNC('month', created_at)")
            ->orderBy('month')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $growth,
        ]);
    }
}
