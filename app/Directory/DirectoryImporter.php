<?php

namespace App\Directory;

use App\Models\Facility;
use App\Models\FacilityService;
use App\Security\AuditLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Imports a KMHFL-style CSV. The real Kenya Master Health Facility List could not be fetched, so this format
 * is AUTHORED here (docs/INTEROPERABILITY.md) and exercised with synthetic fixtures only.
 *
 * Columns: mfl_code,name,facility_type,keph_level,county,sub_county,ward,ownership,operational_status,phone,email,open_hours,services
 * services: values separated by "|". Imported services start UNVERIFIED; existing verification is never overwritten.
 */
final class DirectoryImporter
{
    public const COLUMNS = ['mfl_code', 'name', 'facility_type', 'keph_level', 'county', 'sub_county', 'ward', 'ownership', 'operational_status', 'phone', 'email', 'open_hours', 'services'];
    public const MAX_ROWS = 5000;

    private const TYPE_MAP = [
        'dispensary' => 'dispensary', 'health centre' => 'health_centre', 'health center' => 'health_centre',
        'medical clinic' => 'clinic', 'clinic' => 'clinic', 'sub-county hospital' => 'sub_county_hospital',
        'sub county hospital' => 'sub_county_hospital', 'county referral hospital' => 'county_hospital', 'county hospital' => 'county_hospital',
        'national referral hospital' => 'referral_hospital', 'referral hospital' => 'referral_hospital',
    ];

    /** @return array{created:int,updated:int,errors:list<array{line:int,message:string}>,dry_run:bool} */
    public static function import(string $csvPath, bool $dryRun = false): array
    {
        $fh = fopen($csvPath, 'r');
        if (! $fh) {
            return ['created' => 0, 'updated' => 0, 'errors' => [['line' => 0, 'message' => 'Cannot read file']], 'dry_run' => $dryRun];
        }
        $header = fgetcsv($fh, 0, ',', '"', '');
        $header = array_map(fn ($h) => strtolower(trim((string) $h, "\xEF\xBB\xBF \t")), $header ?: []);
        $missing = array_diff(self::COLUMNS, $header);
        if ($missing) {
            return ['created' => 0, 'updated' => 0, 'errors' => [['line' => 1, 'message' => 'Missing columns: '.implode(', ', $missing)]], 'dry_run' => $dryRun];
        }
        $created = $updated = 0;
        $errors = [];
        $line = 1;
        $seen = [];
        DB::beginTransaction();
        try {
            while (($row = fgetcsv($fh, 0, ',', '"', '')) !== false) {
                $line++;
                if ($line > self::MAX_ROWS + 1) {
                    $errors[] = ['line' => $line, 'message' => 'Row limit exceeded; rest ignored'];
                    break;
                }
                if (count($row) === 1 && trim((string) $row[0]) === '') {
                    continue;
                }
                if (count($row) !== count($header)) {
                    $errors[] = ['line' => $line, 'message' => 'Wrong number of columns'];
                    continue;
                }
                $d = array_combine($header, array_map(fn ($v) => trim((string) $v), $row));
                $v = Validator::make($d, [
                    'mfl_code' => ['required', 'regex:/^\d{4,8}$/'],
                    'name' => ['required', 'max:200'],
                    'keph_level' => ['nullable', 'integer', 'between:2,6'],
                    'email' => ['nullable', 'email'],
                    'phone' => ['nullable', 'regex:/^[0-9 +\-()]{6,20}$/'],
                    'county' => ['required', 'max:80'],
                ]);
                if ($v->fails()) {
                    $errors[] = ['line' => $line, 'message' => implode(' ', $v->errors()->all())];
                    continue;
                }
                if (isset($seen[$d['mfl_code']])) {
                    $errors[] = ['line' => $line, 'message' => 'Duplicate code in file: '.$d['mfl_code']];
                    continue;
                }
                $seen[$d['mfl_code']] = true;
                $type = self::TYPE_MAP[strtolower($d['facility_type'])] ?? 'other';
                $attrs = [
                    'name' => $d['name'], 'type' => $type, 'keph_level' => $d['keph_level'] !== '' ? (int) $d['keph_level'] : null,
                    'county' => $d['county'], 'sub_county' => $d['sub_county'] ?: null, 'ward' => $d['ward'] ?: null,
                    'ownership' => $d['ownership'] ?: null, 'phone' => $d['phone'] ?: null, 'email' => $d['email'] ?: null,
                    'hours' => $d['open_hours'] ?: null, 'is_active' => strtolower($d['operational_status']) === 'operational',
                    'data_origin' => 'synthetic test data',
                ];
                $f = Facility::query()->where('mfl_code', $d['mfl_code'])->first();
                if ($f) {
                    $f->update($attrs);
                    $updated++;
                } else {
                    $f = Facility::query()->create($attrs + ['mfl_code' => $d['mfl_code']]);
                    $created++;
                }
                foreach (array_filter(array_map('trim', explode('|', $d['services']))) as $svc) {
                    FacilityService::query()->firstOrCreate(['facility_id' => $f->id, 'name' => mb_substr($svc, 0, 120)], ['available' => true]);
                }
            }
            $dryRun ? DB::rollBack() : DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        } finally {
            fclose($fh);
        }
        if (! $dryRun) {
            AuditLog::record('directory.import', 'facility', null, ['created' => $created, 'updated' => $updated, 'errors' => count($errors)]);
        }

        return compact('created', 'updated', 'errors') + ['dry_run' => $dryRun];
    }
}
