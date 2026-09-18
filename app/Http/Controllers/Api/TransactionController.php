<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\TransactionIndexRequest;
use App\Http\Resources\AttendanceTransactionResource;
use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TransactionController extends Controller
{
    public function index(TransactionIndexRequest $request): AnonymousResourceCollection
    {
        return $this->transactions($request);
    }

    public function employee(TransactionIndexRequest $request, string $employeeId): AnonymousResourceCollection
    {
        $employeeId = strtoupper($employeeId);

        User::query()->where('employee_id', $employeeId)->firstOrFail();

        return $this->transactions($request, $employeeId);
    }

    protected function transactions(
        TransactionIndexRequest $request,
        ?string $employeeId = null,
    ): AnonymousResourceCollection {
        $filters = $request->validated();

        $firstTransaction = AttendanceRecord::query()
            ->select('daily_first.id')
            ->from('attendance_records as daily_first')
            ->whereColumn('daily_first.user_id', 'attendance_records.user_id')
            ->whereColumn('daily_first.attendance_date', 'attendance_records.attendance_date')
            ->orderBy('daily_first.recorded_at')
            ->orderBy('daily_first.id')
            ->limit(1);

        $lastTransaction = AttendanceRecord::query()
            ->select('daily_last.id')
            ->from('attendance_records as daily_last')
            ->whereColumn('daily_last.user_id', 'attendance_records.user_id')
            ->whereColumn('daily_last.attendance_date', 'attendance_records.attendance_date')
            ->orderByDesc('daily_last.recorded_at')
            ->orderByDesc('daily_last.id')
            ->limit(1);

        $query = AttendanceRecord::query()
            ->with('user:id,name,employee_id')
            ->addSelect([
                'day_first_id' => $firstTransaction,
                'day_last_id' => $lastTransaction,
            ])
            ->when($employeeId, fn (Builder $query, string $id) => $query->where('attendance_records.employee_id', $id))
            ->when($filters['from'] ?? null, fn (Builder $query, string $from) => $query->whereDate('attendance_records.recorded_at', '>=', $from))
            ->when($filters['to'] ?? null, fn (Builder $query, string $to) => $query->whereDate('attendance_records.recorded_at', '<=', $to))
            ->orderBy('attendance_records.recorded_at')
            ->orderBy('attendance_records.id')
            ->paginate((int) ($filters['per_page'] ?? 100))
            ->withQueryString();

        return AttendanceTransactionResource::collection($query);
    }
}
