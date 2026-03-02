<?php

namespace App\Services\Housekeeping;

use App\Models\HousekeepingTask;
use App\Models\Property;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class HousekeepingService
{
    /**
     * Generate daily housekeeping tasks for a property.
     * Called by scheduler at midnight.
     */
    public function generateDailyTasks(Property $property, Carbon $date): int
    {
        $count = 0;

        // ── Checkout cleans: rooms with departures today ──────────────────
        $checkoutReservations = Reservation::where('property_id', $property->id)
            ->where('check_out_date', $date->toDateString())
            ->where('status', Reservation::STATUS_CHECKED_IN)
            ->whereNotNull('room_id')
            ->with('room')
            ->get();

        foreach ($checkoutReservations as $reservation) {
            if ($reservation->room && !$this->taskExists($reservation->room, $date, 'checkout_clean')) {
                HousekeepingTask::create([
                    'property_id'      => $property->id,
                    'room_id'          => $reservation->room_id,
                    'reservation_id'   => $reservation->id,
                    'type'             => 'checkout_clean',
                    'status'           => 'pending',
                    'priority'         => 'high',
                    'scheduled_date'   => $date->toDateString(),
                    'estimated_minutes'=> 45,
                ]);
                $count++;
            }
        }

        // ── Stayover cleans: occupied rooms NOT checking out today ────────
        $inHouseReservations = Reservation::where('property_id', $property->id)
            ->where('status', Reservation::STATUS_CHECKED_IN)
            ->where('check_out_date', '>', $date->toDateString())
            ->whereNotNull('room_id')
            ->get();

        foreach ($inHouseReservations as $reservation) {
            if (!$this->taskExists($reservation->room, $date, 'stayover_clean')) {
                HousekeepingTask::create([
                    'property_id'      => $property->id,
                    'room_id'          => $reservation->room_id,
                    'reservation_id'   => $reservation->id,
                    'type'             => 'stayover_clean',
                    'status'           => 'pending',
                    'priority'         => 'normal',
                    'scheduled_date'   => $date->toDateString(),
                    'estimated_minutes'=> 25,
                ]);
                $count++;
            }
        }

        return $count;
    }

    /**
     * Assign a task to a housekeeper.
     */
    public function assignTask(HousekeepingTask $task, User $housekeeper, User $assignedBy): void
    {
        $task->update([
            'assigned_to' => $housekeeper->id,
            'assigned_by' => $assignedBy->id,
        ]);
    }

    /**
     * Start a housekeeping task.
     */
    public function startTask(HousekeepingTask $task, User $user): void
    {
        $task->update([
            'status'     => 'in_progress',
            'started_at' => now(),
        ]);

        // Update room status
        $task->room->update(['housekeeping_status' => 'dirty']);
    }

    /**
     * Complete a housekeeping task.
     */
    public function completeTask(HousekeepingTask $task, User $user, array $data = []): void
    {
        $startedAt      = $task->started_at ?? now();
        $actualMinutes  = now()->diffInMinutes($startedAt);

        $task->update([
            'status'           => 'completed',
            'completed_at'     => now(),
            'actual_minutes'   => $actualMinutes,
            'completion_notes' => $data['notes'] ?? null,
            'checklist'        => $data['checklist'] ?? null,
        ]);

        // Update room to clean / inspecting based on property setting
        $property = $task->room->property ?? Property::find($task->property_id);
        $newStatus = $property?->getSetting('require_inspection', false) ? 'inspecting' : 'clean';

        $task->room->update([
            'housekeeping_status' => $newStatus,
            'last_cleaned_at'     => now(),
        ]);
    }

    /**
     * Verify / inspect a completed task.
     */
    public function verifyTask(HousekeepingTask $task, User $inspector): void
    {
        $task->update([
            'verified_by' => $inspector->id,
            'verified_at' => now(),
        ]);

        $task->room->update(['housekeeping_status' => 'clean']);
    }

    /**
     * Mark a room as out of order.
     */
    public function markOutOfOrder(Room $room, string $reason, User $user): HousekeepingTask
    {
        $room->update(['housekeeping_status' => Room::HOUSEKEEPING_OUT_OF_ORDER]);

        return HousekeepingTask::create([
            'property_id'    => $room->property_id,
            'room_id'        => $room->id,
            'type'           => 'maintenance',
            'status'         => 'pending',
            'priority'       => 'urgent',
            'notes'          => $reason,
            'scheduled_date' => today()->toDateString(),
            'assigned_by'    => $user->id,
        ]);
    }

    /**
     * Get the housekeeping board for a property on a given date.
     */
    public function getBoard(Property $property, Carbon $date): array
    {
        $tasks = HousekeepingTask::where('property_id', $property->id)
            ->where('scheduled_date', $date->toDateString())
            ->with(['room.roomType', 'assignedTo:id,name', 'reservation.guest:id,first_name,last_name'])
            ->get();

        $rooms = Room::where('property_id', $property->id)
            ->where('is_active', true)
            ->with('roomType:id,name,code')
            ->orderBy('floor')
            ->orderBy('room_number')
            ->get();

        return [
            'date'       => $date->toDateString(),
            'tasks'      => $tasks,
            'rooms'      => $rooms,
            'summary'    => [
                'total'       => $tasks->count(),
                'pending'     => $tasks->where('status', 'pending')->count(),
                'in_progress' => $tasks->where('status', 'in_progress')->count(),
                'completed'   => $tasks->where('status', 'completed')->count(),
                'checkout'    => $tasks->where('type', 'checkout_clean')->count(),
                'stayover'    => $tasks->where('type', 'stayover_clean')->count(),
            ],
        ];
    }

    /**
     * Check if a task already exists for a room on a date.
     */
    private function taskExists(Room $room, Carbon $date, string $type): bool
    {
        return HousekeepingTask::where('room_id', $room->id)
            ->where('scheduled_date', $date->toDateString())
            ->where('type', $type)
            ->whereNotIn('status', ['skipped'])
            ->exists();
    }
}
