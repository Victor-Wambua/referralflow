<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_entries', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('seq')->unique();
            $t->string('occurred_at', 40);
            $t->unsignedBigInteger('actor_id')->nullable()->index();
            $t->string('actor_role', 32)->nullable();
            $t->unsignedBigInteger('facility_id')->nullable()->index();
            $t->string('action', 64)->index();
            $t->string('subject_type', 32)->nullable();
            $t->string('subject_id', 40)->nullable()->index();
            $t->text('reason')->nullable();
            $t->string('flag', 24)->nullable()->index();
            $t->text('meta')->nullable();
            $t->string('prev_hash', 64);
            $t->string('hash', 64);
        });

        $driver = DB::getDriverName();
        if ($driver === 'sqlite') {
            DB::unprepared("CREATE TRIGGER audit_entries_no_update BEFORE UPDATE ON audit_entries BEGIN SELECT RAISE(ABORT, 'audit log is append-only'); END;");
            DB::unprepared("CREATE TRIGGER audit_entries_no_delete BEFORE DELETE ON audit_entries BEGIN SELECT RAISE(ABORT, 'audit log is append-only'); END;");
        } elseif ($driver === 'pgsql') {
            DB::unprepared("CREATE OR REPLACE FUNCTION audit_entries_immutable() RETURNS trigger AS \$\$ BEGIN RAISE EXCEPTION 'audit log is append-only'; END; \$\$ LANGUAGE plpgsql;");
            DB::unprepared('CREATE TRIGGER audit_entries_no_change BEFORE UPDATE OR DELETE ON audit_entries FOR EACH ROW EXECUTE FUNCTION audit_entries_immutable();');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_entries');
    }
};
