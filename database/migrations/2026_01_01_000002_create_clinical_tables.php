<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patients', function (Blueprint $t) {
            $t->id();
            $t->foreignId('facility_id')->constrained();
            $t->text('full_name');            // encrypted
            $t->text('date_of_birth');        // encrypted
            $t->string('sex', 10);
            $t->string('identifier_type', 32)->nullable();
            $t->text('identifier')->nullable(); // encrypted
            $t->string('identifier_hash', 64)->nullable()->index(); // keyed hash, same-facility matching
            $t->string('name_dob_hash', 64)->nullable()->index();
            $t->boolean('is_synthetic')->default(true);
            $t->foreignId('created_by')->nullable()->constrained('users');
            $t->timestamp('anonymised_at')->nullable();
            $t->timestamps();
        });

        Schema::create('referrals', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->foreignId('referring_facility_id')->constrained('facilities');
            $t->foreignId('receiving_facility_id')->constrained('facilities');
            $t->foreignId('created_by')->constrained('users');
            $t->foreignId('patient_id')->nullable()->constrained('patients');
            $t->foreignId('form_schema_id')->nullable()->constrained('referral_form_schemas');
            $t->string('priority', 12);
            $t->string('reason_code', 32);
            $t->text('reason_text')->nullable();     // encrypted
            $t->text('clinical_note')->nullable();   // encrypted
            $t->boolean('include_note')->default(false);
            $t->text('form_data')->nullable();       // encrypted JSON
            $t->string('patient_sex', 10)->nullable();
            $t->unsignedSmallInteger('patient_age')->nullable();
            $t->string('status', 12)->index();
            $t->boolean('stale_confirmed')->default(false);
            $t->text('override_reason')->nullable();
            $t->timestamp('sent_at')->nullable();
            $t->timestamp('received_at')->nullable();
            $t->timestamp('accepted_at')->nullable();
            $t->timestamp('scheduled_at')->nullable();
            $t->timestamp('appointment_at')->nullable();
            $t->timestamp('attended_at')->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->timestamp('declined_at')->nullable();
            $t->timestamp('cancelled_at')->nullable();
            $t->string('terminal_reason', 40)->nullable();
            $t->text('terminal_note')->nullable();   // encrypted
            $t->string('terminal_side', 12)->nullable();
            $t->timestamp('info_requested_at')->nullable();
            $t->timestamp('info_replied_at')->nullable();
            $t->unsignedTinyInteger('escalation_level')->default(0);
            $t->timestamp('last_escalated_at')->nullable();
            $t->timestamp('overdue_flagged_at')->nullable();
            $t->boolean('is_synthetic')->default(true);
            $t->timestamp('purged_at')->nullable();
            $t->timestamps();
            $t->index(['referring_facility_id', 'status']);
            $t->index(['receiving_facility_id', 'status']);
        });

        Schema::create('consents', function (Blueprint $t) {
            $t->id();
            $t->foreignUlid('referral_id')->unique()->constrained('referrals')->cascadeOnDelete();
            $t->foreignId('patient_id')->nullable()->constrained('patients');
            $t->foreignId('recorded_by')->constrained('users');
            $t->foreignId('shared_with_facility_id')->constrained('facilities');
            $t->string('basis', 32);
            $t->boolean('includes_clinical_note')->default(false);
            $t->string('wording_version', 16);
            $t->text('witness')->nullable(); // encrypted
            $t->timestamp('recorded_at');
            $t->timestamp('withdrawn_at')->nullable();
            $t->foreignId('withdrawn_by')->nullable()->constrained('users');
            $t->timestamps();
        });

        Schema::create('referral_events', function (Blueprint $t) {
            $t->id();
            $t->foreignUlid('referral_id')->constrained('referrals')->cascadeOnDelete();
            $t->string('from_status', 12)->nullable();
            $t->string('to_status', 12);
            $t->foreignId('actor_id')->nullable()->constrained('users');
            $t->foreignId('actor_facility_id')->nullable()->constrained('facilities');
            $t->string('reason', 40)->nullable();
            $t->text('note')->nullable(); // encrypted
            $t->timestamp('occurred_at');
            $t->index(['referral_id', 'occurred_at']);
        });

        Schema::create('messages', function (Blueprint $t) {
            $t->id();
            $t->foreignUlid('referral_id')->constrained('referrals')->cascadeOnDelete();
            $t->foreignId('author_id')->constrained('users');
            $t->foreignId('author_facility_id')->constrained('facilities');
            $t->string('kind', 16)->default('message'); // message | info_request
            $t->text('body'); // encrypted
            $t->timestamps();
        });

        Schema::create('counter_referrals', function (Blueprint $t) {
            $t->id();
            $t->foreignUlid('referral_id')->unique()->constrained('referrals')->cascadeOnDelete();
            $t->foreignId('author_id')->constrained('users');
            $t->string('outcome', 32);
            $t->text('summary');          // encrypted
            $t->text('follow_up')->nullable(); // encrypted
            $t->timestamps();
        });

        Schema::create('attachments', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->foreignUlid('referral_id')->constrained('referrals')->cascadeOnDelete();
            $t->foreignId('uploaded_by')->constrained('users');
            $t->foreignId('facility_id')->constrained('facilities');
            $t->string('path');
            $t->text('original_name'); // encrypted
            $t->string('mime', 64);
            $t->unsignedInteger('size');
            $t->string('sha256', 64);
            $t->string('sha1', 40);
            $t->string('scan_status', 16)->default('not_scanned'); // not_scanned | clean | infected
            $t->timestamp('purged_at')->nullable();
            $t->timestamps();
        });

        Schema::create('notifications', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained();
            $t->string('kind', 40);
            $t->ulid('referral_id')->nullable();
            $t->string('text');
            $t->timestamp('read_at')->nullable();
            $t->timestamps();
            $t->index(['user_id', 'read_at']);
        });

        Schema::create('break_glass_grants', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained();
            $t->foreignUlid('referral_id')->constrained('referrals')->cascadeOnDelete();
            $t->text('reason');
            $t->timestamp('granted_at');
            $t->timestamp('expires_at');
            $t->timestamp('revoked_at')->nullable();
            $t->string('review_outcome', 16)->nullable(); // appropriate | inappropriate
            $t->foreignId('reviewed_by')->nullable()->constrained('users');
            $t->timestamp('reviewed_at')->nullable();
            $t->text('review_note')->nullable();
            $t->timestamps();
        });

        Schema::create('legal_holds', function (Blueprint $t) {
            $t->id();
            $t->foreignUlid('referral_id')->nullable()->constrained('referrals');
            $t->foreignId('patient_id')->nullable()->constrained('patients');
            $t->string('reason', 500);
            $t->foreignId('placed_by')->constrained('users');
            $t->timestamp('placed_at');
            $t->timestamp('released_at')->nullable();
            $t->foreignId('released_by')->nullable()->constrained('users');
            $t->timestamps();
        });

        Schema::create('data_requests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('facility_id')->constrained();
            $t->foreignId('patient_id')->constrained('patients');
            $t->string('type', 16); // export | deletion
            $t->foreignId('requested_by')->constrained('users');
            $t->string('status', 16)->default('open'); // open | approved | rejected | completed | blocked
            $t->foreignId('decided_by')->nullable()->constrained('users');
            $t->text('decision_note')->nullable();
            $t->timestamp('decided_at')->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->timestamp('due_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['data_requests', 'legal_holds', 'break_glass_grants', 'notifications', 'attachments', 'counter_referrals', 'messages', 'referral_events', 'consents', 'referrals', 'patients'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
