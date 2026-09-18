<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_ADMIN = 'admin';

    public const ROLE_EMPLOYEE = 'employee';

    protected $fillable = [
        'name',
        'employee_id',
        'email',
        'password',
        'role',
        'is_inactive',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_inactive' => 'boolean',
        ];
    }

    /**
     * @return HasMany<AttendanceRecord, $this>
     */
    public function attendances(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isEmployee(): bool
    {
        return ! $this->isAdmin();
    }

    public function isActive(): bool
    {
        return $this->is_inactive === null;
    }

    /**
     * Attendance records for a given day, oldest first.
     *
     * @return Collection<int, AttendanceRecord>
     */
    public function attendanceOn(Carbon|string|null $date = null): Collection
    {
        $date = $date instanceof Carbon ? $date : Carbon::parse($date ?? now());

        return $this->attendances()
            ->where('attendance_date', $date->toDateString())
            ->orderBy('recorded_at')
            ->get();
    }

    /**
     * Summarise a single day of attendance.
     *
     * @return array{check_in: ?AttendanceRecord, check_out: ?AttendanceRecord, checkpoints: int, type: string, status: string}
     */
    public function dailySummary(Carbon|string|null $date = null): array
    {
        $records = $this->attendanceOn($date);

        $checkIn = $records->first();
        $checkOut = $records->count() > 1 ? $records->last() : null;

        return [
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'checkpoints' => $records->count(),
            'type' => AttendanceRecord::TYPE_CHECKPOINT,
            'status' => $records->isEmpty() ? 'no_checkpoints' : 'active',
        ];
    }
}
