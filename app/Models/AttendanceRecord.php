<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'employee_id',
    'type',
    'attendance_date',
    'recorded_at',
    'latitude',
    'longitude',
    'accuracy',
    'address',
    'city',
    'state',
    'country',
    'photo',
    'watermarked_photo',
    'device_information',
    'user_agent',
    'ip_address',
])]
class AttendanceRecord extends Model
{
    public const TYPE_CHECK_IN = 'check_in';

    public const TYPE_CHECK_OUT = 'check_out';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'attendance_date' => 'date:Y-m-d',
            'recorded_at' => 'datetime',
            'latitude' => 'float',
            'longitude' => 'float',
            'accuracy' => 'float',
            'device_information' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isCheckIn(): bool
    {
        return $this->type === self::TYPE_CHECK_IN;
    }

    public function typeLabel(): string
    {
        return $this->isCheckIn() ? 'Check In' : 'Check Out';
    }

    /**
     * Short human readable location, e.g. "Ikeja, Lagos".
     */
    public function locationLabel(): string
    {
        $parts = array_filter([$this->city, $this->state]);

        if ($parts !== []) {
            return implode(', ', $parts);
        }

        return $this->address ?: 'Location not available';
    }

    public function accuracyLabel(): string
    {
        if ($this->accuracy === null) {
            return 'Not reported';
        }

        return round($this->accuracy).' m';
    }

    /**
     * Device description built only from what the browser actually reported.
     */
    public function deviceLabel(): string
    {
        $info = $this->device_information ?? [];

        $model = trim((string) ($info['model'] ?? ''));
        $platform = trim((string) ($info['platform'] ?? ''));
        $version = trim((string) ($info['platform_version'] ?? ''));
        $browser = trim((string) ($info['browser'] ?? ''));

        $platformLabel = trim($platform.($version !== '' ? ' '.$version : ''));
        $parts = array_filter([$model ?: null, $platformLabel ?: null]);

        if ($parts !== []) {
            return implode(' · ', $parts);
        }

        return $browser !== '' ? $browser : 'Unknown device';
    }

    /**
     * Full device detail for admin popups and record pages.
     *
     * @return array<string, mixed>
     */
    public function deviceDetails(): array
    {
        return $this->device_information ?? [];
    }

    public function coordinateLabel(): string
    {
        return number_format($this->latitude, 6, '.', '').', '.number_format($this->longitude, 6, '.', '');
    }

    /**
     * URL of the stored attendance image (watermarked by default).
     */
    public function photoUrl(string $variant = 'watermarked'): ?string
    {
        $path = $variant === 'original' ? $this->photo : $this->watermarked_photo;

        if (! $path) {
            return null;
        }

        return route('attendance.photo', ['record' => $this->id, 'variant' => $variant]);
    }

    /**
     * Compact payload used by the administrator maps.
     *
     * @return array<string, mixed>
     */
    public function mapPoint(): array
    {
        return [
            'id' => $this->id,
            'lat' => $this->latitude,
            'lng' => $this->longitude,
            'type' => $this->type,
            'type_label' => $this->typeLabel(),
            'employee' => $this->user?->name,
            'employee_id' => $this->employee_id,
            'time' => $this->recorded_at->format('H:i'),
            'time_long' => $this->recorded_at->format('h:i A'),
            'date' => $this->recorded_at->format('d M Y'),
            'accuracy' => $this->accuracyLabel(),
            'location' => $this->locationLabel(),
            'address' => $this->address,
            'coordinates' => $this->coordinateLabel(),
            'device' => $this->deviceLabel(),
            'photo' => $this->photoUrl(),
            'photo_original' => $this->photoUrl('original'),
        ];
    }

    /**
     * Apply the administrator dashboard filters.
     *
     * @param  array<string, mixed>  $filters
     */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['from'] ?? null, fn (Builder $q, $from) => $q->where('attendance_date', '>=', $from))
            ->when($filters['to'] ?? null, fn (Builder $q, $to) => $q->where('attendance_date', '<=', $to))
            ->when($filters['employee'] ?? null, fn (Builder $q, $employee) => $q->where('user_id', $employee))
            ->when($filters['employee_code'] ?? null, fn (Builder $q, $code) => $q->where('employee_id', 'like', '%'.$code.'%'))
            ->when($filters['type'] ?? null, fn (Builder $q, $type) => $q->where('type', $type));
    }
}
