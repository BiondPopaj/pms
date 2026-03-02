<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ─── Room Types ───────────────────────────────────────────────────────
        Schema::create('room_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->string('name');                          // Deluxe King, Standard Twin
            $table->string('code', 20);                     // DK, ST, etc.
            $table->text('description')->nullable();
            $table->integer('base_occupancy')->default(2);
            $table->integer('max_occupancy')->default(3);
            $table->integer('max_adults')->default(2);
            $table->integer('max_children')->default(1);
            $table->string('bed_type')->nullable();          // king, twin, double, queen
            $table->decimal('size_sqm', 8, 2)->nullable();
            $table->json('amenities')->nullable();           // ["wifi","tv","minibar"]
            $table->json('photos')->nullable();
            $table->decimal('base_rate', 12, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['property_id', 'code']);
            $table->index(['property_id', 'is_active']);
        });

        // ─── Physical Rooms ───────────────────────────────────────────────────
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_type_id')->constrained()->cascadeOnDelete();
            $table->string('room_number', 20);
            $table->string('floor', 10)->nullable();
            $table->string('building', 50)->nullable();
            $table->string('view_type')->nullable();         // sea, garden, pool, city
            $table->string('housekeeping_status')->default('clean'); // clean, dirty, inspecting, out_of_order
            $table->string('occupancy_status')->default('vacant');   // vacant, occupied, departing, arriving
            $table->boolean('is_smoking')->default(false);
            $table->boolean('is_accessible')->default(false);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->foreignId('assigned_housekeeper_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_cleaned_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['property_id', 'room_number']);
            $table->index(['property_id', 'housekeeping_status']);
            $table->index(['property_id', 'occupancy_status']);
            $table->index(['property_id', 'room_type_id']);
        });

        // ─── Rate Plans ───────────────────────────────────────────────────────
        Schema::create('rate_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->string('name');                          // BAR, Corporate, Package
            $table->string('code', 20);
            $table->text('description')->nullable();
            $table->string('meal_plan')->default('room_only'); // room_only, bed_breakfast, half_board, full_board, all_inclusive
            $table->boolean('is_refundable')->default(true);
            $table->integer('cancellation_days')->default(0);  // days before arrival for free cancel
            $table->decimal('cancellation_penalty', 5, 2)->default(0); // % of first night
            $table->boolean('is_active')->default(true);
            $table->boolean('is_public')->default(true);    // visible on booking engine
            $table->json('conditions')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['property_id', 'code']);
            $table->index(['property_id', 'is_active']);
        });

        // ─── Rate Plan Room Type Pricing ──────────────────────────────────────
        Schema::create('rate_plan_room_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rate_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_type_id')->constrained()->cascadeOnDelete();
            $table->decimal('rate', 12, 2)->default(0);     // base rate
            $table->decimal('extra_adult_rate', 12, 2)->default(0);
            $table->decimal('extra_child_rate', 12, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['rate_plan_id', 'room_type_id']);
            $table->index(['property_id', 'rate_plan_id']);
        });

        // ─── Room Rate Calendar (daily overrides) ─────────────────────────────
        Schema::create('room_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rate_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_type_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->decimal('rate', 12, 2)->default(0);
            $table->integer('availability')->nullable();     // null = use room count
            $table->boolean('closed')->default(false);       // stop-sell
            $table->boolean('closed_to_arrival')->default(false);
            $table->boolean('closed_to_departure')->default(false);
            $table->integer('min_stay')->default(1);
            $table->integer('max_stay')->nullable();
            $table->timestamps();

            $table->unique(['property_id', 'rate_plan_id', 'room_type_id', 'date']);
            $table->index(['property_id', 'date']);
            $table->index(['rate_plan_id', 'date']);
        });

        // ─── Guests ───────────────────────────────────────────────────────────
        Schema::create('guests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('nationality', 2)->nullable();
            $table->string('language', 5)->default('en');
            $table->date('date_of_birth')->nullable();
            $table->string('gender')->nullable();            // male, female, other
            $table->string('address_line1')->nullable();
            $table->string('address_line2')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('country', 2)->nullable();
            $table->string('company_name')->nullable();
            $table->string('vat_number')->nullable();
            $table->string('passport_number')->nullable();   // stored encrypted
            $table->string('id_type')->nullable();           // passport, driver_license, national_id
            $table->string('id_number')->nullable();         // encrypted
            $table->date('id_expiry')->nullable();
            $table->string('id_document_path')->nullable();  // encrypted file path
            $table->decimal('total_revenue', 14, 2)->default(0);
            $table->integer('total_stays')->default(0);
            $table->string('vip_status')->nullable();        // silver, gold, platinum
            $table->text('notes')->nullable();
            $table->text('internal_notes')->nullable();
            $table->boolean('is_blacklisted')->default(false);
            $table->string('blacklist_reason')->nullable();
            $table->json('preferences')->nullable();         // pillow type, floor pref, etc.
            $table->timestamps();
            $table->softDeletes();

            $table->index(['property_id', 'email']);
            $table->index(['property_id', 'last_name', 'first_name']);
            $table->index(['property_id', 'phone']);
            // Trigram index for fuzzy search (created in raw SQL seeder)
        });

        // ─── Booking Sources ──────────────────────────────────────────────────
        Schema::create('booking_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->string('name');                          // Direct, Booking.com, Expedia
            $table->string('code', 30);                     // DIRECT, BOOKING, EXPEDIA
            $table->string('type')->default('direct');       // direct, ota, gds, corporate, group
            $table->decimal('commission_percent', 5, 2)->default(0);
            $table->string('color', 7)->default('#6171f6');  // for calendar display
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['property_id', 'code']);
            $table->index(['property_id', 'is_active']);
        });

        // ─── Reservations ─────────────────────────────────────────────────────
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->string('reservation_number', 20)->unique();
            $table->string('status')->default('pending'); // pending, confirmed, checked_in, checked_out, no_show, cancelled
            $table->foreignId('guest_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_type_id')->constrained();
            $table->foreignId('room_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('rate_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('booking_source_id')->nullable()->constrained()->nullOnDelete();
            $table->date('check_in_date');
            $table->date('check_out_date');
            $table->integer('nights');
            $table->integer('adults')->default(1);
            $table->integer('children')->default(0);
            $table->integer('infants')->default(0);
            $table->decimal('room_rate', 12, 2)->default(0);  // per night
            $table->decimal('total_room', 14, 2)->default(0);
            $table->decimal('total_extras', 14, 2)->default(0);
            $table->decimal('total_tax', 14, 2)->default(0);
            $table->decimal('total_discount', 14, 2)->default(0);
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->decimal('total_paid', 14, 2)->default(0);
            $table->decimal('balance_due', 14, 2)->default(0);
            $table->string('currency', 3)->default('USD');
            $table->string('ota_confirmation_number')->nullable();
            $table->decimal('ota_commission', 14, 2)->default(0);
            $table->string('payment_status')->default('unpaid'); // unpaid, partial, paid, refunded
            $table->text('special_requests')->nullable();
            $table->text('internal_notes')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamp('checked_out_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->decimal('cancellation_fee', 12, 2)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('checked_in_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('checked_out_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_group_booking')->default(false);
            $table->foreignId('group_id')->nullable()->index();
            $table->json('extras')->nullable();              // additional charges snapshot
            $table->json('metadata')->nullable();            // OTA payload, etc.
            $table->timestamps();
            $table->softDeletes();

            // Core indexes for performance
            $table->index(['property_id', 'status']);
            $table->index(['property_id', 'check_in_date', 'check_out_date']);
            $table->index(['property_id', 'guest_id']);
            $table->index(['property_id', 'room_id']);
            $table->index(['property_id', 'room_type_id']);
            $table->index(['property_id', 'booking_source_id']);
            $table->index(['property_id', 'payment_status']);
            $table->index('reservation_number');
            $table->index('ota_confirmation_number');
            $table->index(['property_id', 'check_in_date']);
            $table->index(['property_id', 'check_out_date']);

            // For overbooking checks
            $table->index(['room_type_id', 'check_in_date', 'check_out_date', 'status']);
        });

        // ─── Reservation Guests (additional guests) ───────────────────────────
        Schema::create('reservation_guests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guest_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->unique(['reservation_id', 'guest_id']);
            $table->index('reservation_id');
        });

        // ─── Reservation Status History ───────────────────────────────────────
        Schema::create('reservation_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->text('reason')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['reservation_id', 'created_at']);
            $table->index('property_id');
        });

        // ─── Folios (billing) ─────────────────────────────────────────────────
        Schema::create('folios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guest_id')->constrained()->cascadeOnDelete();
            $table->string('folio_number', 30)->unique();
            $table->string('status')->default('open');       // open, closed, voided
            $table->decimal('total_charges', 14, 2)->default(0);
            $table->decimal('total_payments', 14, 2)->default(0);
            $table->decimal('balance', 14, 2)->default(0);
            $table->string('currency', 3)->default('USD');
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['property_id', 'status']);
            $table->index(['reservation_id']);
            $table->index('folio_number');
        });

        // ─── Folio Items (charges & payments) ────────────────────────────────
        Schema::create('folio_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('folio_id')->constrained()->cascadeOnDelete();
            $table->string('type');                          // charge, payment, refund, tax, discount
            $table->string('category');                      // room, food, beverage, service, tax, etc.
            $table->text('description');
            $table->decimal('quantity', 10, 3)->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('amount', 14, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('tax_rate', 5, 4)->default(0);
            $table->string('tax_name')->nullable();
            $table->string('payment_method')->nullable();    // cash, card, bank_transfer, ota
            $table->string('payment_reference')->nullable();
            $table->date('charge_date');
            $table->boolean('is_voided')->default(false);
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['folio_id', 'type']);
            $table->index(['property_id', 'charge_date']);
            $table->index(['folio_id', 'is_voided']);
        });

        // ─── Invoices ─────────────────────────────────────────────────────────
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('folio_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guest_id')->constrained()->cascadeOnDelete();
            $table->string('invoice_number', 30)->unique();
            $table->string('status')->default('draft');      // draft, issued, paid, voided
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('tax_total', 14, 2)->default(0);
            $table->decimal('discount_total', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->string('currency', 3)->default('USD');
            $table->date('issue_date');
            $table->date('due_date')->nullable();
            $table->json('billing_address')->nullable();
            $table->json('line_items')->nullable();          // snapshot at time of issue
            $table->string('pdf_path')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['property_id', 'status']);
            $table->index(['property_id', 'issue_date']);
            $table->index('invoice_number');
        });

        // ─── Tax Configurations ───────────────────────────────────────────────
        Schema::create('tax_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->string('name');                          // VAT, City Tax, Service Charge
            $table->string('code', 20);
            $table->string('type')->default('percentage');   // percentage, fixed
            $table->decimal('rate', 8, 4)->default(0);       // percent or fixed amount
            $table->string('applies_to')->default('room');   // room, food, all
            $table->boolean('is_inclusive')->default(false);  // included in price or added on top
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['property_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_configs');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('folio_items');
        Schema::dropIfExists('folios');
        Schema::dropIfExists('reservation_status_history');
        Schema::dropIfExists('reservation_guests');
        Schema::dropIfExists('reservations');
        Schema::dropIfExists('booking_sources');
        Schema::dropIfExists('guests');
        Schema::dropIfExists('room_rates');
        Schema::dropIfExists('rate_plan_room_types');
        Schema::dropIfExists('rate_plans');
        Schema::dropIfExists('rooms');
        Schema::dropIfExists('room_types');
    }
};
