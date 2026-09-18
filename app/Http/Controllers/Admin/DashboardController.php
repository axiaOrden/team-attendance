<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $this->filters($request);
        $summaries = $this->summaryQuery($filters)
            ->orderByDesc('attendance_date')
            ->orderBy('employee_name')
            ->paginate(20)
            ->withQueryString();

        $latestTimes = AttendanceRecord::query()
            ->whereHas('user', fn (Builder $query) => $query->whereNull('is_inactive'))
            ->filter($filters)
            ->select('attendance_records.user_id')
            ->selectRaw('MAX(attendance_records.recorded_at) as latest_at')
            ->groupBy('attendance_records.user_id');

        $mapRecords = AttendanceRecord::query()
            ->with('user')
            ->joinSub($latestTimes, 'latest_checkpoints', fn ($join) => $join
                ->on('latest_checkpoints.user_id', '=', 'attendance_records.user_id')
                ->on('latest_checkpoints.latest_at', '=', 'attendance_records.recorded_at'))
            ->select('attendance_records.*')
            ->orderBy('attendance_records.user_id')
            ->get();

        $checkpointTotals = AttendanceRecord::query()
            ->whereHas('user', fn (Builder $query) => $query->whereNull('is_inactive'))
            ->filter($filters)
            ->selectRaw('user_id, COUNT(*) as aggregate')
            ->groupBy('user_id')
            ->pluck('aggregate', 'user_id');

        $summaryStats = DB::query()
            ->fromSub($this->summaryQuery($filters), 'daily_attendance')
            ->selectRaw('COUNT(DISTINCT user_id) as employees')
            ->selectRaw('COUNT(*) as check_ins')
            ->selectRaw('SUM(CASE WHEN checkpoint_count >= 2 THEN 1 ELSE 0 END) as check_outs')
            ->selectRaw('SUM(checkpoint_count) as checkpoints')
            ->first();

        $averageSeconds = DB::query()
            ->fromSub($this->summaryQuery($filters), 'daily_attendance')
            ->where('checkpoint_count', '>=', 2)
            ->avg(DB::raw($this->workingSecondsExpression()));

        return view('admin.dashboard', [
            'filters' => $filters,
            'summaries' => $summaries,
            'employees' => $this->employees(),
            'points' => $mapRecords->map(fn (AttendanceRecord $record) => $record->mapPoint([
                'checkpoint_total' => (int) ($checkpointTotals[$record->user_id] ?? 0),
                'activity_url' => route('admin.trail', ['employee' => $record->user_id, 'date' => $record->attendance_date->toDateString()]),
            ]))->values(),
            'activityDates' => DB::table('attendance_records')
                ->join('users', 'users.id', '=', 'attendance_records.user_id')
                ->whereNull('users.is_inactive')
                ->distinct()
                ->orderBy('attendance_records.attendance_date')
                ->pluck('attendance_records.attendance_date')
                ->map(fn ($date) => (string) $date),
            'stats' => [
                'employees' => (int) ($summaryStats->employees ?? 0),
                'check_ins' => (int) ($summaryStats->check_ins ?? 0),
                'check_outs' => (int) ($summaryStats->check_outs ?? 0),
                'checkpoints' => (int) ($summaryStats->checkpoints ?? 0),
                'average_hours' => $this->durationLabel($averageSeconds),
            ],
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $this->filters($request);
        $filename = 'attendance-'.$filters['from'].'-to-'.$filters['to'].'.csv';

        return response()->streamDownload(function () use ($filters): void {
            $stream = fopen('php://output', 'w');
            fputcsv($stream, ['Employee ID', 'Employee Name', 'Date', 'Check In', 'Check Out', 'Working Hours', 'Number of Checkpoints']);

            $this->summaryQuery($filters)
                ->orderBy('attendance_date')
                ->orderBy('employee_name')
                ->chunk(500, function (Collection $rows) use ($stream): void {
                    foreach ($rows as $row) {
                        fputcsv($stream, [
                            $row->employee_id,
                            $row->employee_name,
                            $row->attendance_date,
                            $row->check_in_at ? date('H:i:s', strtotime($row->check_in_at)) : '',
                            $row->checkpoint_count >= 2 ? date('H:i:s', strtotime($row->latest_checkpoint_at)) : '',
                            $this->workingHoursLabel($row->check_in_at, $row->checkpoint_count >= 2 ? $row->latest_checkpoint_at : null),
                            $row->checkpoint_count,
                        ]);
                    }
                });

            fclose($stream);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    protected function summaryQuery(array $filters): QueryBuilder
    {
        $query = AttendanceRecord::query()
            ->join('users', 'users.id', '=', 'attendance_records.user_id')
            ->whereNull('users.is_inactive')
            ->filter($filters)
            ->select([
                'attendance_records.user_id',
                'attendance_records.employee_id',
                'attendance_records.attendance_date',
                'users.name as employee_name',
            ])
            ->selectRaw('MIN(attendance_records.recorded_at) as check_in_at')
            ->selectRaw('MAX(attendance_records.recorded_at) as latest_checkpoint_at')
            ->selectRaw('COUNT(*) as checkpoint_count')
            ->groupBy('attendance_records.user_id', 'attendance_records.employee_id', 'attendance_records.attendance_date', 'users.name');

        return $query
            ->when($filters['status'] === 'open', fn (Builder $builder) => $builder->havingRaw('COUNT(*) = 1'))
            ->when($filters['status'] === 'complete', fn (Builder $builder) => $builder->havingRaw('COUNT(*) >= 2'))
            ->toBase();
    }

    protected function employees(): \Illuminate\Database\Eloquent\Collection
    {
        return User::query()
            ->where('role', User::ROLE_EMPLOYEE)
            ->whereNull('is_inactive')
            ->orderBy('name')
            ->get(['id', 'name', 'employee_id']);
    }

    protected function filters(Request $request): array
    {
        $data = $request->validate([
            'date' => ['nullable', 'date'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'employee' => ['nullable', 'integer', 'exists:users,id'],
            'employee_code' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', 'in:check_in,checkpoint,check_out'],
            'status' => ['nullable', 'in:open,complete'],
        ]);

        $from = $data['from'] ?? $data['date'] ?? today()->toDateString();
        $to = $data['to'] ?? $data['date'] ?? $from;

        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        return [
            'date' => $from === $to ? $from : null,
            'from' => $from,
            'to' => $to,
            'employee' => $data['employee'] ?? null,
            'employee_code' => ! empty($data['employee_code']) ? trim($data['employee_code']) : null,
            'type' => $data['type'] ?? null,
            'status' => $data['status'] ?? null,
        ];
    }

    protected function workingSecondsExpression(): string
    {
        return DB::getDriverName() === 'sqlite'
            ? '(julianday(latest_checkpoint_at) - julianday(check_in_at)) * 86400'
            : 'TIMESTAMPDIFF(SECOND, check_in_at, latest_checkpoint_at)';
    }

    protected function workingHoursLabel(?string $checkIn, ?string $checkOut): string
    {
        if (! $checkIn || ! $checkOut) {
            return '';
        }

        return $this->durationLabel(strtotime($checkOut) - strtotime($checkIn));
    }

    protected function durationLabel(float|int|null $seconds): string
    {
        if ($seconds === null) {
            return '—';
        }

        $minutes = (int) round($seconds / 60);

        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }
}
