<?php

namespace App\Http\Controllers;

use App\Domain\MetricsService;
use App\Security\AuditLog;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function show(Request $request)
    {
        [$m, $days] = $this->metrics($request);

        return view('dashboard', ['m' => $m, 'days' => $days, 'scopeLabel' => $this->scoped($request) ? 'your facility' : 'the network']);
    }

    public function export(Request $request)
    {
        [$m] = $this->metrics($request);
        AuditLog::record('dashboard.export', 'metrics', null, ['from' => $m['from'], 'to' => $m['to']], null, 'export');
        $rows = [['metric', 'value']];
        foreach ($m['overall'] as $k => $v) {
            $rows[] = [$k, is_float($v) ? round($v, 3) : $v];
        }
        foreach ($m['facilities'] as $f) {
            $rows[] = ['facility:'.$f['facility'].':received_n', $f['received_n']];
            $rows[] = ['facility:'.$f['facility'].':suppressed', $f['suppressed'] ? 'yes' : 'no'];
        }
        $out = fopen('php://temp', 'r+');
        foreach ($rows as $r) {
            fputcsv($out, $r, ',', '"', '');
        }
        rewind($out);

        return response(stream_get_contents($out), 200, ['Content-Type' => 'text/csv', 'Content-Disposition' => 'attachment; filename="referralflow-aggregate.csv"']);
    }

    private function scoped(Request $request): bool
    {
        return in_array($request->user()->role->value, ['coordinator', 'facility_admin'], true);
    }

    private function metrics(Request $request): array
    {
        $days = in_array((int) $request->input('days'), [7, 30, 90, 365], true) ? (int) $request->input('days') : 30;
        $to = now()->addSecond()->startOfSecond();
        $m = MetricsService::compute(now()->subDays($days)->startOfDay(), $to, $this->scoped($request) ? $request->user()->facility_id : null);

        return [$m, $days];
    }
}
