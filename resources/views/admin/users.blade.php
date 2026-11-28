@extends('layouts.app')
@section('title', __('Users and roles'))
@section('content')
<h1>{{ __('Users and roles') }}</h1>
<div class="banner info"><div>{{ __('Two-step sign-in is required for every account and cannot be turned off. Changes here need re-authentication and are logged.') }}</div></div>
<div class="card tw" role="region" tabindex="0" aria-label="{{ __('Users and roles') }}"><table class="stack"><thead><tr><th>{{ __('Name') }}</th><th>{{ __('Role') }}</th><th>{{ __('Facility') }}</th><th>{{ __('Status') }}</th><th>{{ __('Actions') }}</th></tr></thead><tbody>
@foreach($users as $u)
<tr><td data-label="{{ __('Name') }}" class="fac">{{ $u->name }}<br><small class="mute">{{ $u->email }}</small></td><td data-label="{{ __('Role') }}">{{ __($u->role->label()) }}</td><td data-label="{{ __('Facility') }}">{{ $u->facility?->name ?? '—' }}</td><td data-label="{{ __('Status') }}">{{ $u->status === 'active' ? __('Active') : __('Suspended') }}@if(!$u->mfa_enabled_at) <span class="pill p-warn">{{ __('MFA not set up') }}</span>@endif</td>
<td data-label="{{ __('Actions') }}">@if($u->id !== auth()->id())
<form method="post" action="{{ route('admin.users.update', $u) }}" class="inline">@csrf<label class="sr" for="role-{{ $u->id }}">{{ __('Role') }}</label><select id="role-{{ $u->id }}" name="role" style="width:auto;min-height:40px">@foreach($roles as $r)<option value="{{ $r->value }}" @selected($u->role===$r)>{{ __($r->label()) }}</option>@endforeach</select> <button class="btn sm quiet">{{ __('Change') }}</button></form>
<form method="post" action="{{ route('admin.users.suspend', $u) }}" class="inline">@csrf<button class="btn sm quiet">{{ $u->status==='active' ? __('Suspend') : __('Reactivate') }}</button></form>
<form method="post" action="{{ route('admin.users.mfa', $u) }}" class="inline">@csrf<button class="btn sm quiet">{{ __('Reset MFA') }}</button></form>
<form method="post" action="{{ route('admin.users.password', $u) }}" class="inline">@csrf<button class="btn sm quiet">{{ __('Reset password') }}</button></form>
@else<span class="mute">{{ __('You') }}</span>@endif</td></tr>
@endforeach
</tbody></table></div>
<form method="post" action="{{ route('admin.users.store') }}" class="card" novalidate><h2>{{ __('Invite a user') }}</h2>@csrf
<x-field name="name" :label="__('Name')" required />
<x-field name="email" type="email" :label="__('Work email')" required />
<div class="field"><label for="role">{{ __('Role') }}</label><select id="role" name="role">@foreach($roles as $r)<option value="{{ $r->value }}" @selected(old('role')===$r->value)>{{ __($r->label()) }}</option>@endforeach</select>@error('role')<div class="err" role="alert">{{ $message }}</div>@enderror</div>
@if($facilities)<div class="field"><label for="facility_id">{{ __('Facility (not needed for analysts and platform admins)') }}</label><select id="facility_id" name="facility_id"><option value="">—</option>@foreach($facilities as $f)<option value="{{ $f->id }}" @selected(old('facility_id')==$f->id)>{{ $f->name }}</option>@endforeach</select>@error('facility_id')<div class="err" role="alert">{{ $message }}</div>@enderror</div>@endif
<button class="btn">{{ __('Create user') }}</button></form>
@endsection
