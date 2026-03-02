<?php

namespace App\Http\Controllers\Api\V1\Properties;

use App\Http\Controllers\Controller;
use App\Models\NightAudit;
use App\Services\NightAudit\NightAuditService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NightAuditController extends Controller
{
    public function __construct(
        private readonly NightAuditService $nightAuditService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $property = $request->get('current_property');

        $audits = NightAudit::where('property_id', $property->id)
            ->with('completedBy:id,name')
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('audit_date')
            ->paginate($request->integer('per_page', 30));

        return response()->json([
            'success' => true,
            'data'    => $audits->items(),
            'meta'    => [
                'total'        => $audits->total(),
                'current_page' => $audits->currentPage(),
                'last_page'    => $audits->lastPage(),
            ],
        ]);
    }

    public function show(Request $request, string $date): JsonResponse
    {
        $property = $request->get('current_property');
        $audit    = NightAudit::where('property_id', $property->id)
            ->where('audit_date', $date)
            ->with('completedBy:id,name')
            ->first();

        if (!$audit) {
            return response()->json(['success' => false, 'message' => 'Audit not found for this date.'], 404);
        }

        return response()->json(['success' => true, 'data' => $audit]);
    }

    public function run(Request $request, string $date): JsonResponse
    {
        $this->authorize('runNightAudit', $request->get('current_property'));

        try {
            $audit = $this->nightAuditService->runAudit(
                $request->get('current_property'),
                Carbon::parse($date),
                $request->user()
            );
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => "Night audit for {$date} completed successfully.",
            'data'    => $audit,
        ]);
    }

    public function report(Request $request, string $date): JsonResponse
    {
        $report = $this->nightAuditService->getReport($request->get('current_property'), Carbon::parse($date));

        return response()->json([
            'success' => true,
            'data'    => $report,
        ]);
    }
}
