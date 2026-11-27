<?php

namespace Database\Seeders;

use App\Directory\DirectoryImporter;
use App\Enums\Role;
use App\Models\Consent;
use App\Models\CounterReferral;
use App\Models\Facility;
use App\Models\FormSchema;
use App\Models\Message;
use App\Models\Patient;
use App\Models\Referral;
use App\Models\ReferralEvent;
use App\Models\User;
use App\Security\AuditLog;
use App\Security\BlindIndex;
use App\Security\TenantContext;
use App\Security\Totp;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/** SYNTHETIC network: 10 facilities, 32 users, ~200 referrals through every status. Deterministic (seeded RNG). */
class DemoSeeder extends Seeder
{
    public const PASSWORD = 'Demo-Passphrase-2026';

    private const FIRST = ['Amina', 'Joseph', 'Grace', 'Peter', 'Faith', 'Samuel', 'Mercy', 'Daniel', 'Esther', 'Brian', 'Lucy', 'Kevin', 'Naomi', 'Hassan', 'Zawadi', 'Otieno', 'Wanjiru', 'Kamau', 'Achieng', 'Musa'];

    private const LAST = ['Wekesa', 'Otieno', 'Njeri', 'Mwangi', 'Akinyi', 'Kiptoo', 'Mutiso', 'Odhiambo', 'Chebet', 'Barasa', 'Wafula', 'Hussein', 'Nyambura', 'Karanja', 'Atieno'];

    public function run(): void
    {
        mt_srand(4242);
        TenantContext::asSystem(function () {
            DirectoryImporter::import(database_path('fixtures/facilities-synthetic.csv'));
            $this->verifyFacilities();
            $this->users();
            $this->referrals();
            AuditLog::record('seed.run', 'system', null, ['note' => 'synthetic demonstration data']);
        });
    }

    public static function secretFor(string $email): string
    {
        return Totp::base32Encode(substr(hash('sha256', 'demo-totp|'.$email, true), 0, 20));
    }

    private function verifyFacilities(): void
    {
        $days = ['900001' => 2, '900002' => 1, '900003' => 45, '900004' => 3, '900005' => null, '900006' => 10, '900007' => 5, '900008' => 1, '900009' => 40, '900010' => 6];
        foreach (Facility::query()->get() as $f) {
            $d = $days[$f->mfl_code] ?? null;
            $at = $d === null ? null : now()->subDays($d);
            $f->forceFill(['info_verified_at' => $at])->save();
            foreach ($f->services as $i => $s) {
                $s->forceFill(['verified_at' => $at, 'available' => ! ($f->mfl_code === '900003' && $i === 1)])->save();
            }
        }
    }

    private function users(): void
    {
        $slug = fn (Facility $f) => strtolower(preg_replace('/[^a-z]/i', '', explode(' ', $f->name)[0]));
        $i = 0;
        foreach (Facility::query()->orderBy('id')->get() as $f) {
            foreach ([Role::Clinician, Role::Coordinator, Role::FacilityAdmin] as $role) {
                $this->user(self::FIRST[$i % 20].' '.self::LAST[($i * 7) % 15], $role->value.'.'.$slug($f).'@demo.referralflow.test', $role, $f->id);
                $i++;
            }
        }
        $this->user('Network Analyst Demo', 'analyst@demo.referralflow.test', Role::NetworkAnalyst, null);
        $this->user('Platform Admin Demo', 'platform@demo.referralflow.test', Role::PlatformAdmin, null);
    }

    private function user(string $name, string $email, Role $role, ?int $facilityId): void
    {
        User::query()->create([
            'name' => $name, 'email' => $email, 'password' => self::PASSWORD, 'role' => $role, 'facility_id' => $facilityId,
            'status' => 'active', 'mfa_secret' => self::secretFor($email), 'mfa_enabled_at' => now(),
        ]);
    }

    private function referrals(): void
    {
        $facilities = Facility::query()->orderBy('id')->get();
        $users = User::query()->get()->groupBy('facility_id');
        $schema = FormSchema::active();
        $pats = [];
        foreach ($facilities as $f) {
            for ($k = 0; $k < 14; $k++) {
                $name = self::FIRST[mt_rand(0, 19)].' '.self::LAST[mt_rand(0, 14)];
                $dob = Carbon::now()->subYears(mt_rand(0, 80))->subDays(mt_rand(0, 300))->toDateString();
                $sex = ['female', 'male'][mt_rand(0, 1)];
                $idv = 'SYN-'.str_pad((string) mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
                $pats[$f->id][] = Patient::query()->create([
                    'facility_id' => $f->id, 'full_name' => $name, 'date_of_birth' => $dob, 'sex' => $sex,
                    'identifier_type' => 'facility_record', 'identifier' => $idv,
                    'identifier_hash' => BlindIndex::make('identifier:facility_record', $idv),
                    'name_dob_hash' => BlindIndex::nameDob($name, $dob, $sex), 'is_synthetic' => true,
                ]);
            }
        }
        $plan = ['completed' => 42, 'declined' => 26, 'cancelled' => 26, 'accepted' => 16, 'scheduled' => 16, 'attended' => 10, 'received' => 14, 'sent' => 24, 'created' => 26];
        $reasons = array_keys(config('referralflow.reason_codes'));
        $decl = array_keys(config('referralflow.decline_reasons'));
        $canc = array_keys(config('referralflow.cancel_reasons'));
        $outs = array_keys(config('referralflow.outcomes'));
        $texts = ['Synthetic case: needs specialist review', 'Synthetic case: imaging required', 'Synthetic case: higher level of care', 'Synthetic case: follow-up of test result'];

        foreach ($plan as $status => $count) {
            for ($n = 0; $n < $count; $n++) {
                $from = $facilities[mt_rand(0, 9)];
                do {
                    $to = $facilities[mt_rand(0, 9)];
                } while ($to->id === $from->id);
                $patient = $pats[$from->id][mt_rand(0, 13)];
                $prio = ['emergency', 'urgent', 'routine', 'routine'][mt_rand(0, 3)];
                $recent = in_array($status, ['sent', 'received', 'created']);
                $createdAt = Carbon::now()->subMinutes($recent ? mt_rand(30, 60 * 72) : mt_rand(60 * 24 * 3, 60 * 24 * 85));
                $creator = $users[$from->id]->firstWhere('role', Role::Clinician);
                $recv = $users[$to->id]->firstWhere('role', Role::Coordinator);
                $r = new Referral([
                    'referring_facility_id' => $from->id, 'receiving_facility_id' => $to->id, 'created_by' => $creator->id,
                    'patient_id' => $patient->id, 'form_schema_id' => $schema->id, 'priority' => $prio,
                    'reason_code' => $reasons[mt_rand(0, count($reasons) - 1)], 'reason_text' => $texts[mt_rand(0, 3)],
                    'include_note' => $incl = (mt_rand(0, 2) > 0), 'clinical_note' => $incl ? 'Synthetic clinical note for demonstration only.' : null,
                    'form_data' => ['treatment_given' => 'Synthetic: first-line care given', 'allergies' => 'none', 'transport_needed' => (bool) mt_rand(0, 1)],
                    'patient_sex' => $patient->sex, 'patient_age' => $patient->ageYears(), 'status' => 'created', 'is_synthetic' => true,
                ]);
                $r->created_at = $createdAt;
                $r->updated_at = $createdAt;
                $r->save();
                $events = [[null, 'created', $creator, null, $createdAt]];
                $t = $createdAt->copy();
                $set = [];
                $mv = function (string $from_, string $to_, $actor, $reason, Carbon $at) use (&$events) {
                    $events[] = [$from_, $to_, $actor, $reason, $at];
                };

                if ($status !== 'created') {
                    $t = $createdAt->copy()->addMinutes(mt_rand(2, 40));
                    $set['sent_at'] = $t->copy();
                    $mv('created', 'sent', $creator, null, $t->copy());
                    Consent::query()->create(['referral_id' => $r->id, 'patient_id' => $patient->id, 'recorded_by' => $creator->id, 'shared_with_facility_id' => $to->id, 'basis' => ['written', 'verbal_witnessed', 'guardian'][mt_rand(0, 2)], 'includes_clinical_note' => $incl, 'wording_version' => 'v1-draft-counsel-review-pending', 'recorded_at' => $t]);
                }
                $cur = 'sent';
                $step = fn (int $lo, int $hi) => $t = $t->copy()->addMinutes(mt_rand($lo, $hi));
                $ackMax = ['emergency' => 25, 'urgent' => 200, 'routine' => 1800][$prio];
                if (in_array($status, ['received', 'accepted', 'scheduled', 'attended', 'completed'])) {
                    $step(1, $ackMax);
                    $set['received_at'] = $t->copy();
                    $mv('sent', 'received', $recv, null, $t->copy());
                    $cur = 'received';
                }
                if (in_array($status, ['accepted', 'scheduled', 'attended', 'completed'])) {
                    $step(2, $ackMax);
                    $set['accepted_at'] = $t->copy();
                    $mv('received', 'accepted', $recv, null, $t->copy());
                    $cur = 'accepted';
                }
                if (in_array($status, ['scheduled', 'attended', 'completed'])) {
                    $step(10, 600);
                    $set['scheduled_at'] = $t->copy();
                    $set['appointment_at'] = $t->copy()->addDays(mt_rand(1, 6));
                    $mv('accepted', 'scheduled', $recv, null, $t->copy());
                    $cur = 'scheduled';
                }
                if (in_array($status, ['attended', 'completed'])) {
                    $t = $set['appointment_at']->copy()->addHours(mt_rand(1, 5));
                    $set['attended_at'] = $t->copy();
                    $mv('scheduled', 'attended', $recv, null, $t->copy());
                    $cur = 'attended';
                }
                if ($status === 'completed') {
                    $step(60, 60 * 30);
                    $set['completed_at'] = $t->copy();
                    $mv('attended', 'completed', $recv, null, $t->copy());
                    CounterReferral::query()->create(['referral_id' => $r->id, 'author_id' => $recv->id, 'outcome' => $outs[mt_rand(0, count($outs) - 1)], 'summary' => 'Synthetic summary: patient seen, plan agreed.', 'follow_up' => null]);
                }
                if ($status === 'declined') {
                    $step(5, $ackMax);
                    $reason = $decl[mt_rand(0, count($decl) - 1)];
                    $set += ['declined_at' => $t->copy(), 'terminal_reason' => $reason, 'terminal_side' => 'receiver'];
                    $mv('sent', 'declined', $recv, $reason, $t->copy());
                }
                if ($status === 'cancelled') {
                    $viaReceiver = mt_rand(0, 2) === 0;
                    $step(30, 60 * 48);
                    $reason = $viaReceiver ? 'patient_did_not_attend' : $canc[mt_rand(0, count($canc) - 1)];
                    $set += ['cancelled_at' => $t->copy(), 'terminal_reason' => $reason, 'terminal_side' => $viaReceiver ? 'receiver' : 'referrer'];
                    $mv('sent', 'cancelled', $viaReceiver ? $recv : $creator, $reason, $t->copy());
                }
                if (in_array($status, ['sent', 'received']) && isset($set['sent_at']) && $set['sent_at']->diffInMinutes(now()) > ['emergency' => 120, 'urgent' => 1440, 'routine' => 4320][$prio]) {
                    $set['escalation_level'] = 3;
                    $set['overdue_flagged_at'] = now()->subMinutes(5);
                }
                $r->forceFill($set + ['status' => $status])->save();
                foreach ($events as [$f_, $t_, $actor, $reason, $at]) {
                    ReferralEvent::query()->create(['referral_id' => $r->id, 'from_status' => $f_, 'to_status' => $t_, 'actor_id' => $actor->id, 'actor_facility_id' => $actor->facility_id, 'reason' => $reason, 'occurred_at' => $at]);
                }
                if ($status !== 'created' && mt_rand(0, 3) === 0) {
                    $m = Message::query()->create(['referral_id' => $r->id, 'author_id' => $recv->id, 'author_facility_id' => $to->id, 'kind' => 'message', 'body' => 'Synthetic message: please confirm the arrival time.']);
                    $m->forceFill(['created_at' => $t->copy()->subMinutes(5)])->save();
                }
            }
        }
    }
}
