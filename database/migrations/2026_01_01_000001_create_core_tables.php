<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facilities', function (Blueprint $t) {
            $t->id();
            $t->string('mfl_code', 20)->nullable()->unique();
            $t->string('name');
            $t->string('type', 32)->default('other');
            $t->unsignedTinyInteger('keph_level')->nullable();
            $t->string('county')->nullable();
            $t->string('sub_county')->nullable();
            $t->string('ward')->nullable();
            $t->string('ownership')->nullable();
            $t->string('phone', 40)->nullable();
            $t->string('email')->nullable();
            $t->string('hours')->nullable();
            $t->json('capabilities')->nullable();
            $t->boolean('is_active')->default(true);
            $t->string('data_origin', 40)->default('synthetic test data');
            $t->timestamp('info_verified_at')->nullable();
            $t->unsignedBigInteger('info_verified_by')->nullable();
            $t->timestamps();
            $t->index(['county', 'type']);
        });

        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('email')->unique();
            $t->string('password');
            $t->string('role', 32)->index();
            $t->foreignId('facility_id')->nullable()->constrained('facilities');
            $t->string('locale', 5)->default('en');
            $t->string('status', 16)->default('active');
            $t->text('mfa_secret')->nullable();
            $t->timestamp('mfa_enabled_at')->nullable();
            $t->unsignedBigInteger('mfa_last_step')->nullable();
            $t->text('mfa_recovery_codes')->nullable();
            $t->boolean('must_change_password')->default(false);
            $t->unsignedSmallInteger('failed_logins')->default(0);
            $t->timestamp('locked_until')->nullable();
            $t->timestamp('last_login_at')->nullable();
            $t->rememberToken();
            $t->timestamps();
        });

        Schema::create('sessions', function (Blueprint $t) {
            $t->string('id')->primary();
            $t->foreignId('user_id')->nullable()->index();
            $t->string('ip_address', 45)->nullable();
            $t->text('user_agent')->nullable();
            $t->longText('payload');
            $t->integer('last_activity')->index();
        });

        Schema::create('facility_services', function (Blueprint $t) {
            $t->id();
            $t->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->boolean('available')->default(true);
            $t->string('note')->nullable();
            $t->timestamp('verified_at')->nullable();
            $t->foreignId('verified_by')->nullable()->constrained('users');
            $t->timestamps();
            $t->unique(['facility_id', 'name']);
        });

        Schema::create('facility_reports', function (Blueprint $t) {
            $t->id();
            $t->foreignId('facility_id')->constrained();
            $t->foreignId('reported_by')->constrained('users');
            $t->string('message', 500);
            $t->timestamp('resolved_at')->nullable();
            $t->timestamps();
        });

        Schema::create('settings', function (Blueprint $t) {
            $t->string('key')->primary();
            $t->json('value');
            $t->timestamps();
        });

        Schema::create('referral_form_schemas', function (Blueprint $t) {
            $t->id();
            $t->string('key', 64);
            $t->unsignedInteger('version');
            $t->string('name');
            $t->string('status', 16)->default('draft'); // draft | active | retired
            $t->json('fields');
            $t->foreignId('created_by')->nullable()->constrained('users');
            $t->timestamp('activated_at')->nullable();
            $t->timestamps();
            $t->unique(['key', 'version']);
        });

        Schema::create('cache', function (Blueprint $t) {
            $t->string('key')->primary();
            $t->mediumText('value');
            $t->bigInteger('expiration')->index();
        });
        Schema::create('cache_locks', function (Blueprint $t) {
            $t->string('key')->primary();
            $t->string('owner');
            $t->bigInteger('expiration')->index();
        });
        Schema::create('jobs', function (Blueprint $t) {
            $t->id();
            $t->string('queue')->index();
            $t->longText('payload');
            $t->unsignedTinyInteger('attempts');
            $t->unsignedInteger('reserved_at')->nullable();
            $t->unsignedInteger('available_at');
            $t->unsignedInteger('created_at');
        });
        Schema::create('failed_jobs', function (Blueprint $t) {
            $t->id();
            $t->string('uuid')->unique();
            $t->text('connection');
            $t->text('queue');
            $t->longText('payload');
            $t->longText('exception');
            $t->timestamp('failed_at')->useCurrent();
        });
    }

    public function down(): void
    {
        foreach (['failed_jobs', 'jobs', 'cache_locks', 'cache', 'referral_form_schemas', 'settings', 'facility_reports', 'facility_services', 'sessions', 'users', 'facilities'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
