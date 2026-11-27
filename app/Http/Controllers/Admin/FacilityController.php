<?php

namespace App\Http\Controllers\Admin;

use App\Directory\DirectoryImporter;
use App\Http\Controllers\Controller;
use App\Models\Facility;
use App\Models\FacilityReport;
use App\Security\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FacilityController extends Controller
{
    public function index()
    {
        return view('admin.facilities', ['items' => Facility::query()->orderBy('name')->get(), 'reports' => FacilityReport::query()->whereNull('resolved_at')->count(), 'types' => config('referralflow.facility_types')]);
    }

    public function store(Request $request)
    {
        $f = Facility::query()->create($this->validated($request) + ['data_origin' => 'user-submitted']);
        AuditLog::record('facility.create', 'facility', $f->id);

        return back()->with('status', __('Facility registered. It is unverified until a coordinator verifies it.'));
    }

    public function update(Request $request, Facility $facility)
    {
        $facility->update($this->validated($request, $facility));
        AuditLog::record('facility.update', 'facility', $facility->id);

        return back()->with('status', __('Facility updated.'));
    }

    public function import(Request $request)
    {
        $request->validate(['csv' => ['required', 'file', 'max:1024', 'mimes:csv,txt'], 'dry_run' => ['nullable', 'boolean']]);
        $res = DirectoryImporter::import($request->file('csv')->getRealPath(), $request->boolean('dry_run'));

        return back()->with('import', $res);
    }

    private function validated(Request $request, ?Facility $f = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'mfl_code' => ['nullable', 'regex:/^\d{4,8}$/', Rule::unique('facilities', 'mfl_code')->ignore($f?->id)],
            'type' => ['required', Rule::in(config('referralflow.facility_types'))],
            'keph_level' => ['nullable', 'integer', 'between:2,6'],
            'county' => ['required', 'string', 'max:80'],
            'sub_county' => ['nullable', 'string', 'max:80'],
            'phone' => ['nullable', 'regex:/^[0-9 +\-()]{6,20}$/'],
            'email' => ['nullable', 'email', 'max:190'],
            'hours' => ['nullable', 'string', 'max:200'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }
}
