<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Facility;
use App\Models\User;
use App\Security\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $u = $request->user();
        $q = User::query()->with('facility:id,name')->orderBy('name');
        if ($u->role === Role::FacilityAdmin) {
            $q->where('facility_id', $u->facility_id);
        }

        return view('admin.users', ['users' => $q->get(), 'roles' => $this->assignable($u), 'facilities' => $u->role === Role::PlatformAdmin ? Facility::query()->orderBy('name')->get(['id', 'name']) : null]);
    }

    public function store(Request $request)
    {
        $u = $request->user();
        $roles = array_map(fn ($r) => $r->value, $this->assignable($u));
        $d = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'role' => ['required', Rule::in($roles)],
            'facility_id' => [$u->role === Role::PlatformAdmin ? 'nullable' : 'prohibited', 'exists:facilities,id'],
        ]);
        $role = Role::from($d['role']);
        $facilityId = $role->needsFacility() ? ($u->role === Role::FacilityAdmin ? $u->facility_id : ($d['facility_id'] ?? null)) : null;
        if ($role->needsFacility() && ! $facilityId) {
            return back()->withErrors(['facility_id' => __('Choose a facility for this role.')])->withInput();
        }
        $temp = Str::password(16, symbols: false).'a1A';
        $new = User::query()->create([
            'name' => $d['name'], 'email' => strtolower($d['email']), 'password' => $temp, 'role' => $role,
            'facility_id' => $facilityId, 'must_change_password' => true, 'status' => 'active',
        ]);
        AuditLog::record('user.invite', 'user', $new->id, ['role' => $role->value]);

        return back()->with('status', __('User created.'))->with('temp_password', $temp)->with('temp_for', $new->email);
    }

    public function update(Request $request, User $user)
    {
        $this->authorizeTarget($request->user(), $user);
        $roles = array_map(fn ($r) => $r->value, $this->assignable($request->user()));
        $d = $request->validate(['role' => ['required', Rule::in($roles)]]);
        abort_if($user->id === $request->user()->id, 403);
        $role = Role::from($d['role']);
        if ($role->needsFacility() !== $user->role->needsFacility()) {
            return back()->withErrors(['role' => __('That role change needs a different facility setting. Create a new user instead.')]);
        }
        $old = $user->role->value;
        $user->forceFill(['role' => $role])->save();
        AuditLog::record('user.role_change', 'user', $user->id, ['from' => $old, 'to' => $role->value]);

        return back()->with('status', __('Role updated.'));
    }

    public function suspend(Request $request, User $user)
    {
        $this->authorizeTarget($request->user(), $user);
        abort_if($user->id === $request->user()->id, 403);
        $user->forceFill(['status' => $user->status === 'active' ? 'suspended' : 'active'])->save();
        \Illuminate\Support\Facades\DB::table('sessions')->where('user_id', $user->id)->delete();
        AuditLog::record($user->status === 'active' ? 'user.reactivate' : 'user.suspend', 'user', $user->id);

        return back()->with('status', __('Account updated.'));
    }

    public function resetMfa(Request $request, User $user)
    {
        $this->authorizeTarget($request->user(), $user);
        $user->forceFill(['mfa_secret' => null, 'mfa_enabled_at' => null, 'mfa_last_step' => null, 'mfa_recovery_codes' => null])->save();
        \Illuminate\Support\Facades\DB::table('sessions')->where('user_id', $user->id)->delete();
        AuditLog::record('user.mfa_reset', 'user', $user->id);

        return back()->with('status', __('MFA reset. They will set it up again at next sign-in.'));
    }

    public function resetPassword(Request $request, User $user)
    {
        $this->authorizeTarget($request->user(), $user);
        $temp = Str::password(16, symbols: false).'a1A';
        $user->forceFill(['password' => $temp, 'must_change_password' => true, 'failed_logins' => 0, 'locked_until' => null])->save();
        \Illuminate\Support\Facades\DB::table('sessions')->where('user_id', $user->id)->delete();
        AuditLog::record('user.password_reset', 'user', $user->id);

        return back()->with('status', __('Password reset.'))->with('temp_password', $temp)->with('temp_for', $user->email);
    }

    /** @return list<Role> */
    private function assignable(User $u): array
    {
        return $u->role === Role::PlatformAdmin ? Role::cases() : [Role::Clinician, Role::Coordinator];
    }

    private function authorizeTarget(User $actor, User $target): void
    {
        if ($actor->role === Role::PlatformAdmin) {
            return;
        }
        abort_unless($actor->role === Role::FacilityAdmin && $target->facility_id === $actor->facility_id && in_array($target->role, [Role::Clinician, Role::Coordinator], true), 403);
    }
}
