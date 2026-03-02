<?php

namespace App\Http\Controllers\Api\V1\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\WebhookEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ChannelWebhookController extends Controller
{
    public function handle(Request $request, string $channel): JsonResponse
    {
        Log::info("Channel webhook received from {$channel}.", ['payload' => $request->all()]);

        // Store webhook for processing
        \DB::table('webhook_events')->insert([
            'source'       => $channel,
            'event_type'   => $request->header('X-Event-Type', 'unknown'),
            'status'       => 'pending',
            'payload'      => json_encode($request->all()),
            'attempts'     => 0,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        // Queue processing job
        // dispatch(new ProcessChannelWebhook($channel, $request->all()));

        return response()->json(['received' => true]);
    }
}
