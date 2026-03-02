<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\PropertyAdminResource;
use App\Models\Property;
use App\Models\SubscriptionPlan;
use App\Services\Tenant\PropertyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PropertyAdminController extends Controller
{
    public function __construct(
        private readonly PropertyService $propertyService,
    ) {}

    /**
     * GET /api/v1/admin/properties
     */
    public function index(Request $request): JsonResponse
    {
        $properties = Property::query()
            ->with(['subscriptionPlan:id,name,slug'])
            ->when($request->search, fn ($q, $s) =>
                $q->where(fn ($q) => $q
                    ->where('name', 'ilike', "%{$s}%")
                    ->orWhere('email', 'ilike', "%{$s}%")
                    ->orWhere('city', 'ilike', "%{$s}%")
                )
            )
            ->when($request->status, fn ($q, $s) =>
                $q->where('subscription_status', $s)
            )
            ->when($request->plan, fn ($q, $p) =>
                $q->whereHas('subscriptionPlan', fn ($q) => $q->where('slug', $p))
            )
            ->when($request->country, fn ($q, $c) =>
                $q->where('country', $c)
            )
            ->when($request->boolean('active_only'), fn ($q) =>
                $q->where('is_active', true)
            )
            ->withCount(['reservations', 'rooms', 'users'])
            ->latest()
            ->paginate($request->integer('per_page', 25));

        return response()->json([
            'success' => true,
            'data'    => PropertyAdminResource::collection($properties),
            'meta'    => [
                'total'        => $properties->total(),
                'per_page'     => $properties->perPage(),
                'current_page' => $properties->currentPage(),
                'last_page'    => $properties->lastPage(),
            ],
        ]);
    }

    /**
     * POST /api/v1/admin/properties
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'                 => ['required', 'string', 'max:255'],
            'email'                => ['required', 'email', 'unique:properties,email'],
            'phone'                => ['nullable', 'string', 'max:30'],
            'country'              => ['required', 'string', 'size:2'],
            'city'                 => ['nullable', 'string', 'max:100'],
            'timezone'             => ['required', 'string', 'timezone'],
            'currency'             => ['required', 'string', 'size:3'],
            'property_type'        => ['required', 'in:hotel,hostel,resort,motel,villa,apartment'],
            'subscription_plan_id' => ['nullable', 'exists:subscription_plans,id'],
            'owner_name'           => ['required', 'string', 'max:255'],
            'owner_email'          => ['required', 'email'],
            'owner_password'       => ['required', 'string', 'min:8'],
        ]);

        $property = $this->propertyService->createProperty($validated);

        return response()->json([
            'success' => true,
            'message' => 'Property created successfully.',
            'data'    => new PropertyAdminResource($property),
        ], 201);
    }

    /**
     * GET /api/v1/admin/properties/{id}
     */
    public function show(Property $property): JsonResponse
    {
        $property->load([
            'subscriptionPlan',
            'users' => fn ($q) => $q->select('users.id', 'name', 'email', 'is_active')
                                    ->withPivot('role', 'is_active'),
        ]);
        $property->loadCount(['reservations', 'rooms', 'guests']);

        return response()->json([
            'success' => true,
            'data'    => new PropertyAdminResource($property),
        ]);
    }

    /**
     * PATCH /api/v1/admin/properties/{id}
     */
    public function update(Request $request, Property $property): JsonResponse
    {
        $validated = $request->validate([
            'name'                    => ['sometimes', 'string', 'max:255'],
            'email'                   => ['sometimes', 'email', 'unique:properties,email,'.$property->id],
            'is_active'               => ['sometimes', 'boolean'],
            'subscription_plan_id'    => ['sometimes', 'nullable', 'exists:subscription_plans,id'],
            'subscription_status'     => ['sometimes', 'in:trial,active,past_due,cancelled,suspended'],
            'subscription_ends_at'    => ['sometimes', 'nullable', 'date'],
            'feature_flags'           => ['sometimes', 'array'],
        ]);

        $property->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Property updated.',
            'data'    => new PropertyAdminResource($property->fresh(['subscriptionPlan'])),
        ]);
    }

    /**
     * POST /api/v1/admin/properties/{id}/suspend
     */
    public function suspend(Request $request, Property $property): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $property->update([
            'is_active'           => false,
            'subscription_status' => 'suspended',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Property suspended.',
        ]);
    }

    /**
     * POST /api/v1/admin/properties/{id}/activate
     */
    public function activate(Property $property): JsonResponse
    {
        $property->update([
            'is_active'           => true,
            'subscription_status' => 'active',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Property activated.',
        ]);
    }

    /**
     * GET /api/v1/admin/properties/stats
     */
    public function stats(): JsonResponse
    {
        $stats = cache()->remember('admin.property.stats', now()->addMinutes(5), function () {
            return [
                'total'          => Property::count(),
                'active'         => Property::where('is_active', true)->count(),
                'trial'          => Property::where('subscription_status', 'trial')->count(),
                'paid'           => Property::where('subscription_status', 'active')->count(),
                'suspended'      => Property::where('subscription_status', 'suspended')->count(),
                'by_plan'        => Property::select('subscription_plan_id')
                                        ->with('subscriptionPlan:id,name')
                                        ->withCount(['*' => fn ($q) => $q->where('is_active', true)])
                                        ->groupBy('subscription_plan_id')
                                        ->get(),
                'by_country'     => Property::selectRaw('country, count(*) as total')
                                        ->groupBy('country')
                                        ->orderByDesc('total')
                                        ->limit(10)
                                        ->get(),
                'new_this_month' => Property::whereMonth('created_at', now()->month)
                                        ->whereYear('created_at', now()->year)
                                        ->count(),
            ];
        });

        return response()->json([
            'success' => true,
            'data'    => $stats,
        ]);
    }
}
