<?php

namespace App\Http\Controllers\Api\V1\Webhooks;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class StripeWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        $payload   = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $secret    = config('services.stripe.webhook_secret');

        // Verify signature
        try {
            $event = \Stripe\Webhook::constructEvent($payload, $sigHeader, $secret);
        } catch (\Exception $e) {
            Log::warning('Stripe webhook signature verification failed.', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Invalid signature.'], 400);
        }

        Log::info('Stripe webhook received.', ['type' => $event->type]);

        match ($event->type) {
            'invoice.payment_succeeded' => $this->handlePaymentSucceeded($event->data->object),
            'invoice.payment_failed'    => $this->handlePaymentFailed($event->data->object),
            'customer.subscription.deleted' => $this->handleSubscriptionCancelled($event->data->object),
            default                     => null,
        };

        return response()->json(['received' => true]);
    }

    private function handlePaymentSucceeded(object $invoice): void
    {
        // Update property subscription status
        $property = \App\Models\Property::where('stripe_subscription_id', $invoice->subscription)->first();
        if ($property) {
            $property->update(['subscription_status' => 'active']);
        }
    }

    private function handlePaymentFailed(object $invoice): void
    {
        $property = \App\Models\Property::where('stripe_subscription_id', $invoice->subscription)->first();
        if ($property) {
            $property->update(['subscription_status' => 'past_due']);
        }
    }

    private function handleSubscriptionCancelled(object $subscription): void
    {
        $property = \App\Models\Property::where('stripe_subscription_id', $subscription->id)->first();
        if ($property) {
            $property->update([
                'subscription_status' => 'cancelled',
                'subscription_ends_at' => now(),
            ]);
        }
    }
}
