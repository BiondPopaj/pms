<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ─── Housekeeping Tasks ───────────────────────────────────────────────
        Schema::create('housekeeping_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reservation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');                          // checkout_clean, stayover_clean, deep_clean, inspection, maintenance
            $table->string('status')->default('pending');    // pending, in_progress, completed, skipped
            $table->string('priority')->default('normal');   // low, normal, high, urgent
            $table->text('notes')->nullable();
            $table->text('completion_notes')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('scheduled_date');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->integer('estimated_minutes')->nullable();
            $table->integer('actual_minutes')->nullable();
            $table->json('checklist')->nullable();           // [{item:"Towels",done:true}]
            $table->timestamps();
            $table->softDeletes();

            $table->index(['property_id', 'scheduled_date', 'status']);
            $table->index(['property_id', 'assigned_to', 'status']);
            $table->index(['room_id', 'scheduled_date']);
        });

        // ─── Housekeeping Schedules ───────────────────────────────────────────
        Schema::create('housekeeping_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();  // housekeeper
            $table->date('date');
            $table->string('shift')->default('morning');     // morning, afternoon, evening
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['property_id', 'date']);
            $table->index(['user_id', 'date']);
        });

        // ─── Digital Registration Cards ───────────────────────────────────────
        Schema::create('registration_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guest_id')->constrained()->cascadeOnDelete();
            $table->string('token', 64)->unique();           // for guest self-fill link
            $table->string('status')->default('pending');    // pending, sent, completed, signed
            $table->json('guest_data')->nullable();          // captured guest info
            $table->string('signature_path')->nullable();    // encrypted signature image
            $table->string('id_document_path')->nullable();  // encrypted scan
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['reservation_id']);
            $table->index(['property_id', 'status']);
        });

        // ─── Audit Logs ───────────────────────────────────────────────────────
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event');                         // created, updated, deleted, login, etc.
            $table->string('auditable_type')->nullable();
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('url')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->json('tags')->nullable();
            $table->timestamps();

            $table->index(['property_id', 'event', 'created_at']);
            $table->index(['auditable_type', 'auditable_id']);
            $table->index(['user_id', 'created_at']);
            $table->index('created_at');
        });

        // ─── Activity Log (Spatie) ────────────────────────────────────────────
        Schema::create('activity_log', function (Blueprint $table) {
            $table->id();
            $table->string('log_name')->nullable();
            $table->text('description');
            $table->nullableMorphs('subject', 'subject');
            $table->nullableMorphs('causer', 'causer');
            $table->json('properties')->nullable();
            $table->uuid('batch_uuid')->nullable();
            $table->timestamps();

            $table->index('log_name');
            $table->index(['subject_type', 'subject_id']);
            $table->index(['causer_type', 'causer_id']);
        });

        // ─── Night Audits ─────────────────────────────────────────────────────
        Schema::create('night_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->date('audit_date');
            $table->string('status')->default('pending');    // pending, in_progress, completed
            $table->decimal('total_revenue', 14, 2)->default(0);
            $table->decimal('room_revenue', 14, 2)->default(0);
            $table->decimal('other_revenue', 14, 2)->default(0);
            $table->decimal('total_tax', 14, 2)->default(0);
            $table->integer('rooms_occupied')->default(0);
            $table->integer('rooms_available')->default(0);
            $table->decimal('occupancy_rate', 5, 2)->default(0);
            $table->decimal('adr', 12, 2)->default(0);       // Average Daily Rate
            $table->decimal('revpar', 12, 2)->default(0);    // Revenue Per Available Room
            $table->integer('arrivals')->default(0);
            $table->integer('departures')->default(0);
            $table->integer('no_shows')->default(0);
            $table->json('summary')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['property_id', 'audit_date']);
            $table->index(['property_id', 'status']);
        });

        // ─── Channel Manager Connections ──────────────────────────────────────
        Schema::create('channel_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->string('channel');                       // booking_com, expedia, airbnb
            $table->string('status')->default('inactive');   // active, inactive, error
            $table->json('credentials')->nullable();         // encrypted API keys
            $table->json('mapping')->nullable();             // room type / rate plan mapping
            $table->json('settings')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamp('last_error_at')->nullable();
            $table->text('last_error_message')->nullable();
            $table->timestamps();

            $table->unique(['property_id', 'channel']);
            $table->index(['property_id', 'status']);
        });

        // ─── Webhook Events ───────────────────────────────────────────────────
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source');                        // booking_com, expedia, stripe
            $table->string('event_type');
            $table->string('status')->default('pending');    // pending, processing, processed, failed
            $table->json('payload');
            $table->json('response')->nullable();
            $table->integer('attempts')->default(0);
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['property_id', 'status']);
            $table->index(['source', 'event_type']);
            $table->index(['status', 'created_at']);
        });

        // ─── Failed Jobs ──────────────────────────────────────────────────────
        Schema::create('failed_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();

            $table->index('failed_at');
        });

        // ─── Job Batches ──────────────────────────────────────────────────────
        Schema::create('job_batches', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->integer('total_jobs');
            $table->integer('pending_jobs');
            $table->integer('failed_jobs');
            $table->longText('failed_job_ids');
            $table->mediumText('options')->nullable();
            $table->integer('cancelled_at')->nullable();
            $table->integer('created_at');
            $table->integer('finished_at')->nullable();
        });

        // ─── Platform Notifications (skip if Laravel already created it) ────
        if (!Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('type');
                $table->morphs('notifiable');
                $table->text('data');
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
            });
        }

        // ─── Feature Flags ────────────────────────────────────────────────────
        Schema::create('feature_flags', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('enabled_globally')->default(false);
            $table->json('enabled_for_plans')->nullable();  // ["professional","enterprise"]
            $table->json('enabled_for_properties')->nullable(); // specific property IDs
            $table->timestamps();

            $table->index('key');
        });

        // ─── Platform Usage Metrics ───────────────────────────────────────────
        Schema::create('usage_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('metric');                        // api_calls, reservations, logins
            $table->decimal('value', 14, 4)->default(0);
            $table->timestamps();

            $table->unique(['property_id', 'date', 'metric']);
            $table->index(['property_id', 'date']);
            $table->index(['metric', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_metrics');
        Schema::dropIfExists('feature_flags');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('job_batches');
        Schema::dropIfExists('failed_jobs');
        Schema::dropIfExists('webhook_events');
        Schema::dropIfExists('channel_connections');
        Schema::dropIfExists('night_audits');
        Schema::dropIfExists('activity_log');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('registration_cards');
        Schema::dropIfExists('housekeeping_schedules');
        Schema::dropIfExists('housekeeping_tasks');
    }
};
