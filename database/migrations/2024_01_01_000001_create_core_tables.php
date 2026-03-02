<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ─── Subscription Plans ───────────────────────────────────────────────
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');                          // Starter, Professional, Enterprise
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->integer('max_properties')->default(1);
            $table->integer('max_rooms')->default(50);
            $table->integer('max_users')->default(5);
            $table->decimal('price_monthly', 10, 2)->default(0);
            $table->decimal('price_yearly', 10, 2)->default(0);
            $table->string('currency', 3)->default('USD');
            $table->json('features')->nullable();            // enabled features list
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->string('stripe_price_monthly_id')->nullable();
            $table->string('stripe_price_yearly_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'sort_order']);
        });

        // ─── Properties (Tenants) ─────────────────────────────────────────────
        Schema::create('properties', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();                 // public-facing ID
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('email')->unique();
            $table->string('phone', 30)->nullable();
            $table->string('website')->nullable();
            $table->text('description')->nullable();
            $table->string('address_line1')->nullable();
            $table->string('address_line2')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('country', 2)->default('US');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('timezone')->default('UTC');
            $table->string('currency', 3)->default('USD');
            $table->string('locale', 10)->default('en_US');
            $table->string('logo_path')->nullable();
            $table->string('property_type')->default('hotel'); // hotel, hostel, resort, motel, villa
            $table->integer('star_rating')->nullable();
            $table->integer('total_rooms')->default(0);
            $table->string('check_in_time', 5)->default('14:00');
            $table->string('check_out_time', 5)->default('11:00');
            $table->json('settings')->nullable();            // property-level config
            $table->json('invoice_settings')->nullable();    // logo, footer, tax info
            $table->foreignId('subscription_plan_id')->nullable()->constrained('subscription_plans')->nullOnDelete();
            $table->string('subscription_status')->default('trial'); // trial, active, past_due, cancelled, suspended
            $table->timestamp('subscription_starts_at')->nullable();
            $table->timestamp('subscription_ends_at')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->string('stripe_customer_id')->nullable();
            $table->string('stripe_subscription_id')->nullable();
            $table->json('feature_flags')->nullable();       // per-property feature overrides
            $table->boolean('is_active')->default(true);
            $table->boolean('is_verified')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'subscription_status']);
            $table->index('slug');
            $table->index('ulid');
            $table->index('country');
            $table->index('subscription_plan_id');
        });

        // ─── Users ────────────────────────────────────────────────────────────
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('phone', 30)->nullable();
            $table->string('avatar_path')->nullable();
            $table->string('locale', 10)->default('en');
            $table->string('timezone')->default('UTC');
            $table->boolean('is_platform_admin')->default(false);
            $table->boolean('is_active')->default(true);
            $table->boolean('two_factor_enabled')->default(false);
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->json('preferences')->nullable();         // UI preferences (dark mode, etc.)
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['email', 'is_active']);
            $table->index('is_platform_admin');
            $table->index('ulid');
        });

        // ─── Property Users (pivot: user <-> property with role) ──────────────
        Schema::create('property_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role');                          // manager, receptionist, housekeeping, accountant
            $table->json('permissions')->nullable();         // granular overrides
            $table->boolean('is_active')->default(true);
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['property_id', 'user_id']);
            $table->index(['property_id', 'role']);
            $table->index(['user_id', 'is_active']);
        });

        // ─── Personal Access Tokens (Sanctum) ─────────────────────────────────
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('device_name')->nullable();
            $table->timestamps();

            $table->index(['tokenable_id', 'tokenable_type']);
        });

        // ─── Password Reset Tokens ────────────────────────────────────────────
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        // ─── Sessions ─────────────────────────────────────────────────────────
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        // ─── Property Invitations ─────────────────────────────────────────────
        Schema::create('property_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('role');
            $table->string('token', 64)->unique();
            $table->foreignId('invited_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['email', 'token']);
            $table->index(['property_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_invitations');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('property_users');
        Schema::dropIfExists('users');
        Schema::dropIfExists('properties');
        Schema::dropIfExists('subscription_plans');
    }
};
