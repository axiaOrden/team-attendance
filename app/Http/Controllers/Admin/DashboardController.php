<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Filtered attendance dashboard: records as a list and on a map.
     */
    public function index(Request $request): View
    {
        $filters = $this->filters($request);

        $records = AttendanceRecord::query()
            ->with('user')
            ->filter($filters)
            ->orderByDesc('recorded_at')
            ->paginate(25)
            ->withQueryString();

        $mapRecords = AttendanceRecord::query()
            ->with('user')
            ->filter($filters)
            ->orderBy('recorded_at')
            ->limit(500)
            ->get();

        $totals = AttendanceRecord::query()->filter($filters);

        return view('admin.dashboard', [
            'filters' => $filters,
            'records' => $records,
            'employees' => $this->employees(),
            'points' => $mapRecords->map(fn (AttendanceRecord $record) => $record->mapPoint())->values(),
            'mapTruncated' => $mapRecords->count() === 500,
            'stats' => [
                'records' => (clone $totals)->count(),
                'check_ins' => (clone $totals)->where('type', AttendanceRecord::TYPE_CHECK_IN)->count(),
                'check_outs' => (clone $totals)->where('type', AttendanceRecord::TYPE_CHECK_OUT)->count(),
                'employees' => (clone $totals)->distinct()->count('user_id'),
            ],
        ]);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, User>
     */
    protected function employees(): \Illuminate\Database\Eloquent\Collection
    {
        return User::query()
            ->orderBy('name')
            ->get(['id', 'name', 'employee_id', 'role']);
    }

    /**
     * Normalise the dashboard filters from the query string.
     *
     * @return array<string, mixed>
     */
    protected function filters(Request $request): array
    {
        $data = $request->validate([
            'date' => ['nullable', 'date'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'employee' => ['nullable', 'integer', 'exists:users,id'],
            'employee_code' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', 'in:check_in,check_out'],
        ]);

        $from = $data['from'] ?? null;
        $to = $data['to'] ?? null;

        // A single date is simply a range of one day.
        if (! empty($data['date'])) {
            $from = $data['date'];
            $to = $data['date'];
        }

        if ($from && $to && $from > $to) {
            [$from, $to] = [$to, $from];
        }

        return [
            'date' => $data['date'] ?? null,
            'from' => $from,
            'to' => $to,
            'employee' => $data['employee'] ?? null,
            'employee_code' => ! empty($data['employee_code']) ? trim($data['employee_code']) : null,
            'type' => $data['type'] ?? null,
        ];
    }
}
