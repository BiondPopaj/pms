<?php

namespace App\Services\Billing;

use App\Models\Folio;
use App\Models\FolioItem;
use App\Models\Invoice;
use App\Models\Property;
use App\Models\Reservation;
use App\Models\TaxConfig;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BillingService
{
    /**
     * Create an open folio for a reservation.
     */
    public function createFolio(Reservation $reservation): Folio
    {
        return Folio::create([
            'property_id'    => $reservation->property_id,
            'reservation_id' => $reservation->id,
            'guest_id'       => $reservation->guest_id,
            'folio_number'   => $this->generateFolioNumber($reservation->property),
            'status'         => 'open',
            'currency'       => $reservation->currency,
        ]);
    }

    /**
     * Add a charge to a folio.
     */
    public function addCharge(
        Folio  $folio,
        string $category,
        string $description,
        float  $amount,
        float  $quantity = 1,
        ?User  $createdBy = null,
        ?string $chargeDate = null
    ): FolioItem {
        $item = FolioItem::create([
            'property_id'  => $folio->property_id,
            'folio_id'     => $folio->id,
            'type'         => 'charge',
            'category'     => $category,
            'description'  => $description,
            'quantity'     => $quantity,
            'unit_price'   => $amount,
            'amount'       => $amount * $quantity,
            'charge_date'  => $chargeDate ?? today()->toDateString(),
            'created_by'   => $createdBy?->id,
        ]);

        $this->recalculateFolioBalance($folio);

        return $item;
    }

    /**
     * Record a payment on a folio.
     */
    public function recordPayment(
        Folio  $folio,
        float  $amount,
        string $paymentMethod,
        string $description = 'Payment',
        ?string $reference = null,
        ?User   $createdBy = null
    ): FolioItem {
        $item = FolioItem::create([
            'property_id'       => $folio->property_id,
            'folio_id'          => $folio->id,
            'type'              => 'payment',
            'category'          => 'payment',
            'description'       => $description,
            'quantity'          => 1,
            'unit_price'        => $amount,
            'amount'            => -$amount, // negative = reduces balance
            'payment_method'    => $paymentMethod,
            'payment_reference' => $reference,
            'charge_date'       => today()->toDateString(),
            'created_by'        => $createdBy?->id,
        ]);

        $this->recalculateFolioBalance($folio);

        // Update reservation payment status
        $this->updateReservationPaymentStatus($folio->reservation);

        return $item;
    }

    /**
     * Issue an invoice from a folio.
     */
    public function issueInvoice(Folio $folio, User $issuedBy): Invoice
    {
        return DB::transaction(function () use ($folio, $issuedBy) {
            $folio->load(['items' => fn ($q) => $q->where('is_voided', false)]);

            $charges   = $folio->items->where('type', 'charge')->sum('amount');
            $taxes     = $folio->items->where('type', 'tax')->sum('amount');
            $discounts = $folio->items->where('type', 'discount')->sum('amount');
            $total     = $charges + $taxes - $discounts;

            $invoice = Invoice::create([
                'property_id'    => $folio->property_id,
                'folio_id'       => $folio->id,
                'guest_id'       => $folio->guest_id,
                'invoice_number' => $this->generateInvoiceNumber($folio->property),
                'status'         => 'issued',
                'subtotal'       => $charges,
                'tax_total'      => $taxes,
                'discount_total' => $discounts,
                'total'          => $total,
                'currency'       => $folio->currency,
                'issue_date'     => today(),
                'due_date'       => today()->addDays(30),
                'line_items'     => $folio->items->toArray(),
                'issued_at'      => now(),
                'issued_by'      => $issuedBy->id,
            ]);

            return $invoice;
        });
    }

    /**
     * Void a folio item.
     */
    public function voidItem(FolioItem $item, User $voidedBy): void
    {
        $item->update([
            'is_voided' => true,
            'voided_at' => now(),
            'voided_by' => $voidedBy->id,
        ]);

        $this->recalculateFolioBalance($item->folio);
    }

    /**
     * Post nightly room charges to folio.
     */
    public function postNightlyRoomCharge(Reservation $reservation, string $date): FolioItem
    {
        $folio = $reservation->folio;

        return $this->addCharge(
            folio:       $folio,
            category:    'room',
            description: "Room charge – {$reservation->room?->room_number} – {$date}",
            amount:      $reservation->room_rate,
            chargeDate:  $date
        );
    }

    /**
     * Recalculate and save folio balance.
     */
    private function recalculateFolioBalance(Folio $folio): void
    {
        $folio->refresh();
        $folio->load(['items' => fn ($q) => $q->where('is_voided', false)]);

        $total_charges  = $folio->items->whereIn('type', ['charge', 'tax'])->sum('amount');
        $total_payments = abs($folio->items->whereIn('type', ['payment', 'refund'])->sum('amount'));
        $balance        = $total_charges - $total_payments;

        $folio->update([
            'total_charges'  => $total_charges,
            'total_payments' => $total_payments,
            'balance'        => $balance,
        ]);
    }

    /**
     * Update reservation payment status based on balance.
     */
    private function updateReservationPaymentStatus(Reservation $reservation): void
    {
        $folio = $reservation->folio;
        if (!$folio) return;

        $status = match (true) {
            $folio->balance <= 0            => 'paid',
            $folio->total_payments > 0      => 'partial',
            default                          => 'unpaid',
        };

        $reservation->update([
            'total_paid'     => $folio->total_payments,
            'balance_due'    => max(0, $folio->balance),
            'payment_status' => $status,
        ]);
    }

    /**
     * Generate unique folio number.
     */
    private function generateFolioNumber(Property $property): string
    {
        $prefix = $property->getSetting('folio_prefix', 'FOL');

        do {
            $number = $prefix.'-'.date('Ymd').'-'.strtoupper(Str::random(4));
        } while (Folio::where('folio_number', $number)->exists());

        return $number;
    }

    /**
     * Generate unique invoice number.
     */
    private function generateInvoiceNumber(Property $property): string
    {
        $prefix = $property->getSetting('invoice_prefix', 'INV');
        $count  = Invoice::where('property_id', $property->id)->count() + 1;

        return $prefix.'-'.date('Y').'-'.str_pad($count, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Calculate taxes for a given amount.
     */
    public function calculateTaxes(Property $property, float $amount): array
    {
        $taxes      = TaxConfig::where('property_id', $property->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $totalTax   = 0;
        $breakdown  = [];

        foreach ($taxes as $tax) {
            if ($tax->type === 'percentage') {
                $taxAmount = $tax->is_inclusive
                    ? $amount - ($amount / (1 + $tax->rate))
                    : $amount * $tax->rate;
            } else {
                $taxAmount = $tax->rate;
            }

            $totalTax += $taxAmount;
            $breakdown[] = [
                'name'   => $tax->name,
                'code'   => $tax->code,
                'rate'   => $tax->rate,
                'amount' => round($taxAmount, 2),
            ];
        }

        return [round($totalTax, 2), $breakdown];
    }
}
