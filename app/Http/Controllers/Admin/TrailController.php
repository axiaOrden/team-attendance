<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class TrailController extends Controller
{
    /**
     * OpenStreetMap trail of every attendance event of one employee on one day.
     */
    public function index(Request $request): View
    {
        $data = $request->validate([
            'employee' => ['nullable', 'integer', 'exists:users,id'],
            'date' => ['nullable', 'string', 'max:20'],
        ]);

        $employees = User::query()
            ->orderBy('name')
            ->get(['id', 'name', 'employee_id', 'role']);

        // Default to the employee who recorded attendance most recently so the
        // page is useful on first load, then fall back to the first user.
        $mostRecentEmployeeId = AttendanceRecord::query()->orderByDesc('recorded_at')->value('user_id');

        $employee = $employees->firstWhere('id', (int) ($data['employee'] ?? 0))
            ?? $employees->firstWhere('id', $mostRecentEmployeeId)
            ?? $employees->first();

        $date = $this->resolveDate($data['date'] ?? null);

        $records = $employee
            ? $employee->attendanceOn($date)
            : collect();

        return view('admin.trail', [
            'employees' => $employees,
            'employee' => $employee,
            'date' => $date,
            'records' => $records,
            'points' => $records->map(fn (AttendanceRecord $record) => $record->mapPoint())->values(),
        ]);
    }

    /**
     * Accept "today", "yesterday" or a Y-m-d date.
     */
    protected function resolveDate(?string $value): Carbon
    {
        $value = trim((string) $value);

        return match (true) {
            $value === '' || $value === 'today' => today(),
            $value === 'yesterday' => today()->subDay(),
            default => rescue(fn () => Carbon::parse($value)->startOfDay(), today(), report: false),
        };
    }
}
