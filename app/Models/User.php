<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
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

    /**
     * Attendance records for a given day, oldest first.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, AttendanceRecord>
     */
    public function attendanceOn(Carbon|string|null $date = null): \Illuminate\Database\Eloquent\Collection
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
     * @return array{check_in: ?AttendanceRecord, check_out: ?AttendanceRecord, type: ?string, status: string}
     */
    public function dailySummary(Carbon|string|null $date = null): array
    {
        $records = $this->attendanceOn($date);

        $checkIn = $records->firstWhere('type', AttendanceRecord::TYPE_CHECK_IN);
        $checkOut = $records->where('type', AttendanceRecord::TYPE_CHECK_OUT)->last();

        return [
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'type' => $checkOut ? null : ($checkIn ? AttendanceRecord::TYPE_CHECK_OUT : AttendanceRecord::TYPE_CHECK_IN),
            'status' => match (true) {
                $checkIn && $checkOut => 'checked_out',
                (bool) $checkIn => 'checked_in',
                default => 'not_checked_in',
            },
        ];
    }
}
