<?php

namespace App\Http\Controllers\Api\V1\Properties;

use App\Http\Controllers\Controller;
use App\Models\HousekeepingTask;
use App\Models\Reservation;
use App\Models\Room;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HousekeepingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $property = $request->get('current_property');
        $date     = $request->get('date', today()->toDateString());

        $tasks = HousekeepingTask::where('property_id', $property->id)
            ->with(['room:id,room_number,floor', 'assignedTo:id,name', 'reservation:id,reservation_number'])
            ->whereDate('scheduled_date', $date)
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->assigned_to, fn ($q, $id) => $q->where('assigned_to', $id))
            ->orderByRaw("CASE status WHEN 'in_progress' THEN 0 WHEN 'pending' THEN 1 WHEN 'completed' THEN 2 ELSE 3 END")
            ->get();

        return response()->json(['success' => true, 'data' => $tasks]);
    }

    public function board(Request $request): JsonResponse
    {
        $property = $request->get('current_property');

        $rooms = Room::where('property_id', $property->id)
            ->where('is_active', true)
            ->with(['roomType:id,name,code', 'assignedHousekeeper:id,name',
                    'housekeepingTasks' => fn ($q) => $q->whereDate('scheduled_date', today())])
            ->orderBy('room_number')
            ->get()
            ->groupBy('housekeeping_status');

        return response()->json(['success' => true, 'data' => $rooms]);
    }

    public function myTasks(Request $request): JsonResponse
    {
        $tasks = HousekeepingTask::where('property_id', $request->get('current_property')->id)
            ->where('assigned_to', $request->user()->id)
            ->with(['room:id,room_number,floor', 'reservation:id,reservation_number'])
            ->whereDate('scheduled_date', today())
            ->whereIn('status', [HousekeepingTask::STATUS_PENDING, HousekeepingTask::STATUS_IN_PROGRESS])
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 WHEN 'normal' THEN 2 ELSE 3 END")
            ->get();

        return response()->json(['success' => true, 'data' => $tasks]);
    }

    public function createTask(Request $request): JsonResponse
    {
        $property  = $request->get('current_property');
        $validated = $request->validate([
            'room_id'           => ['required', 'exists:rooms,id'],
            'reservation_id'    => ['nullable', 'exists:reservations,id'],
            'type'              => ['required', 'in:checkout_clean,stayover_clean,deep_clean,inspection,maintenance'],
            'priority'          => ['sometimes', 'in:low,normal,high,urgent'],
            'notes'             => ['nullable', 'string'],
            'assigned_to'       => ['nullable', 'exists:users,id'],
            'scheduled_date'    => ['required', 'date'],
            'estimated_minutes' => ['nullable', 'integer', 'min:1'],
            'checklist'         => ['nullable', 'array'],
        ]);

        $task = HousekeepingTask::create([
            ...$validated,
            'property_id' => $property->id,
            'assigned_by' => $request->user()->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Task created.',
            'data'    => $task->load(['room:id,room_number', 'assignedTo:id,name']),
        ], 201);
    }

    public function updateTask(Request $request, HousekeepingTask $task): JsonResponse
    {
        $validated = $request->validate([
            'assigned_to'       => ['sometimes', 'nullable', 'exists:users,id'],
            'priority'          => ['sometimes', 'in:low,normal,high,urgent'],
            'notes'             => ['sometimes', 'nullable', 'string'],
            'scheduled_date'    => ['sometimes', 'date'],
            'estimated_minutes' => ['sometimes', 'nullable', 'integer'],
            'checklist'         => ['sometimes', 'nullable', 'array'],
        ]);

        $task->update($validated);

        return response()->json(['success' => true, 'message' => 'Task updated.', 'data' => $task->fresh()]);
    }

    public function startTask(Request $request, HousekeepingTask $task): JsonResponse
    {
        if (!$task->start($request->user()->id)) {
            return response()->json(['success' => false, 'message' => 'Task cannot be started in its current status.'], 422);
        }

        return response()->json(['success' => true, 'message' => 'Task started.', 'data' => $task->fresh()]);
    }

    public function completeTask(Request $request, HousekeepingTask $task): JsonResponse
    {
        $validated = $request->validate([
            'notes'     => ['nullable', 'string'],
            'checklist' => ['nullable', 'array'],
        ]);

        if (!$task->complete($validated)) {
            return response()->json(['success' => false, 'message' => 'Task cannot be completed in its current status.'], 422);
        }

        return response()->json(['success' => true, 'message' => 'Task completed.', 'data' => $task->fresh()]);
    }

    public function verifyTask(Request $request, HousekeepingTask $task): JsonResponse
    {
        if (!$task->verify($request->user()->id)) {
            return response()->json(['success' => false, 'message' => 'Task is not yet completed.'], 422);
        }

        return response()->json(['success' => true, 'message' => 'Task verified.', 'data' => $task->fresh()]);
    }

    public function generateDailyTasks(Request $request): JsonResponse
    {
        $property = $request->get('current_property');
        $date     = $request->get('date', today()->toDateString());
        $created  = 0;

        // Checkouts - need cleaning after departure
        $departures = Reservation::where('property_id', $property->id)
            ->where('status', Reservation::STATUS_CHECKED_IN)
            ->where('check_out_date', $date)
            ->whereNotNull('room_id')
            ->get();

        foreach ($departures as $res) {
            $exists = HousekeepingTask::where('room_id', $res->room_id)
                ->where('scheduled_date', $date)
                ->where('type', HousekeepingTask::TYPE_CHECKOUT_CLEAN)
                ->exists();

            if (!$exists) {
                HousekeepingTask::create([
                    'property_id'    => $property->id,
                    'room_id'        => $res->room_id,
                    'reservation_id' => $res->id,
                    'type'           => HousekeepingTask::TYPE_CHECKOUT_CLEAN,
                    'priority'       => HousekeepingTask::PRIORITY_HIGH,
                    'scheduled_date' => $date,
                ]);
                $created++;
            }
        }

        // Stayover cleaning - in-house not departing today
        $inHouse = Reservation::where('property_id', $property->id)
            ->where('status', Reservation::STATUS_CHECKED_IN)
            ->where('check_out_date', '>', $date)
            ->whereNotNull('room_id')
            ->get();

        foreach ($inHouse as $res) {
            $exists = HousekeepingTask::where('room_id', $res->room_id)
                ->where('scheduled_date', $date)
                ->exists();

            if (!$exists) {
                HousekeepingTask::create([
                    'property_id'    => $property->id,
                    'room_id'        => $res->room_id,
                    'reservation_id' => $res->id,
                    'type'           => HousekeepingTask::TYPE_STAYOVER_CLEAN,
                    'priority'       => HousekeepingTask::PRIORITY_NORMAL,
                    'scheduled_date' => $date,
                ]);
                $created++;
            }
        }

        return response()->json([
            'success' => true,
            'message' => "{$created} housekeeping tasks generated for {$date}.",
            'tasks_created' => $created,
        ]);
    }
}
