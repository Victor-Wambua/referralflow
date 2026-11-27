<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\Referral;
use App\Models\UserNotice;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function home(Request $request)
    {
        $u = $request->user();
        return match ($u->role) {
            Role::Coordinator => redirect()->route('inbox'),
            Role::NetworkAnalyst => redirect()->route('dashboard'),
            Role::FacilityAdmin => redirect()->route('admin.users'),
            Role::PlatformAdmin => redirect()->route('admin.facilities'),
            default => $this->clinician($request),
        };
    }

    private function clinician(Request $request)
    {
        $u = $request->user();
        $mine = fn () => Referral::query()->where('referring_facility_id', $u->facility_id);
        $attention = $mine()->where(function ($w) {
            $w->where(fn ($d) => $d->where('status', 'declined')->where('declined_at', '>=', now()->subDays(14)))
                ->orWhere(fn ($i) => $i->whereNotNull('info_requested_at')->whereNull('info_replied_at')->whereNotIn('status', ['completed', 'declined', 'cancelled']))
                ->orWhere(fn ($o) => $o->where('escalation_level', '>=', 3)->whereIn('status', ['sent', 'received']));
        })->orderByDesc('updated_at')->limit(10)->get();
        $drafts = $mine()->where('status', 'created')->count();
        $recent = $mine()->orderByDesc('created_at')->limit(5)->get();

        return view('home', ['attention' => $attention, 'recent' => $recent, 'drafts' => $drafts, 'unread' => UserNotice::query()->where('user_id', $u->id)->whereNull('read_at')->count()]);
    }

    public function notifications(Request $request)
    {
        $items = UserNotice::query()->where('user_id', $request->user()->id)->orderByDesc('id')->limit(50)->get();

        return view('notifications', compact('items'));
    }

    public function readNotifications(Request $request)
    {
        UserNotice::query()->where('user_id', $request->user()->id)->whereNull('read_at')->update(['read_at' => now()]);

        return back();
    }

    public function settings(Request $request)
    {
        return view('settings');
    }

    public function updateSettings(Request $request)
    {
        $d = $request->validate(['locale' => ['required', 'in:en,sw']]);
        $request->user()->forceFill(['locale' => $d['locale']])->save();
        $request->session()->put('locale', $d['locale']);

        return back()->with('status', __('Language saved.'));
    }

    public function setGuestLocale(Request $request)
    {
        $d = $request->validate(['locale' => ['required', 'in:en,sw']]);
        $request->session()->put('locale', $d['locale']);

        return back();
    }

    public function health()
    {
        try {
            \Illuminate\Support\Facades\DB::select('select 1');
            $db = 'ok';
        } catch (\Throwable) {
            $db = 'error';
        }

        return response()->json(['status' => $db === 'ok' ? 'ok' : 'degraded', 'database' => $db, 'mode' => config('referralflow.mode'), 'synthetic_data_only' => true], $db === 'ok' ? 200 : 503);
    }

    public function offline()
    {
        return view('offline');
    }
}
