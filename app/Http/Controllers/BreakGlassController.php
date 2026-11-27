<?php

namespace App\Http\Controllers;

use App\Domain\Notifier;
use App\Enums\Role;
use App\Models\BreakGlassGrant;
use App\Models\Referral;
use App\Security\AuditLog;
use App\Security\TenantContext;
use Illuminate\Http\Request;

class BreakGlassController extends Controller
{
    public function request(Request $request, string $id)
    {
        $user = $request->user();
        $min = (int) config('referralflow.break_glass.min_reason_length');
        $d = $request->validate([
            'reason' => ['required', 'string', 'min:'.$min, 'max:500'],
            'confirm_emergency' => ['accepted'],
        ]);
        $referral = TenantContext::asSystem(fn () => Referral::query()->find($id));
        $generic = back()->withErrors(['reason' => __('Could not open this referral.')]);
        if (! $referral || ! $user->isClinical() || $referral->referring_facility_id === $user->facility_id || $referral->receiving_facility_id === $user->facility_id) {
            AuditLog::record('break_glass.denied', 'referral', $id);

            return $generic;
        }
        $grant = BreakGlassGrant::query()->create([
            'user_id' => $user->id, 'referral_id' => $referral->id, 'reason' => $d['reason'],
            'granted_at' => now(), 'expires_at' => now()->addMinutes((int) config('referralflow.break_glass.minutes')),
        ]);
        AuditLog::record('break_glass.grant', 'referral', $referral->id, ['grant' => $grant->id, 'minutes' => config('referralflow.break_glass.minutes')], $d['reason'], 'break_glass');
        Notifier::toFacility($user->facility_id, 'break_glass', null, [Role::FacilityAdmin]);

        return redirect()->route('referrals.show', $referral->id);
    }

    public function index(Request $request)
    {
        $u = $request->user();
        $q = BreakGlassGrant::query()->with(['user:id,name,facility_id,email', 'referral:id'])->orderByDesc('id');
        if ($u->role === Role::FacilityAdmin) {
            $q->whereIn('user_id', \App\Models\User::query()->where('facility_id', $u->facility_id)->select('id'));
        }

        return view('admin.breakglass', ['items' => $q->limit(100)->get()]);
    }

    public function review(Request $request, BreakGlassGrant $grant)
    {
        $u = $request->user();
        $d = $request->validate(['outcome' => ['required', 'in:appropriate,inappropriate'], 'note' => ['required', 'string', 'max:500']]);
        $same = $u->role === Role::FacilityAdmin && $grant->user->facility_id === $u->facility_id;
        abort_unless($same || $u->role === Role::PlatformAdmin, 403);
        abort_if($grant->user_id === $u->id || $grant->reviewed_at, 403);
        $grant->forceFill(['review_outcome' => $d['outcome'], 'reviewed_by' => $u->id, 'reviewed_at' => now(), 'review_note' => $d['note']])->save();
        AuditLog::record('break_glass.review', 'referral', $grant->referral_id, ['grant' => $grant->id, 'outcome' => $d['outcome']], $d['note'], 'break_glass');

        return back()->with('status', __('Review saved.'));
    }
}
