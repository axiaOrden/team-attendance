<?php

namespace Database\Seeders;

use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Development only: a couple of employees with attendance history so the
 * administrator dashboard and the trail map have something to show.
 */
class DemoAttendanceSeeder extends Seeder
{
    /**
     * @var array<int, array<string, mixed>>
     */
    protected array $places = [
        'ikeja' => [
            'latitude' => 6.60180000,
            'longitude' => 3.35150000,
            'address' => 'Allen Avenue, Ikeja, Lagos',
            'city' => 'Ikeja',
        ],
        'victoria_island' => [
            'latitude' => 6.42810000,
            'longitude' => 3.42190000,
            'address' => 'Adeola Odeku Street, Victoria Island, Lagos',
            'city' => 'Victoria Island',
        ],
        'yaba' => [
            'latitude' => 6.50950000,
            'longitude' => 3.37120000,
            'address' => 'Herbert Macaulay Way, Yaba, Lagos',
            'city' => 'Yaba',
        ],
        'surulere' => [
            'latitude' => 6.50000000,
            'longitude' => 3.35000000,
            'address' => 'Bode Thomas Street, Surulere, Lagos',
            'city' => 'Surulere',
        ],
        'lekki' => [
            'latitude' => 6.44780000,
            'longitude' => 3.47230000,
            'address' => 'Admiralty Way, Lekki, Lagos',
            'city' => 'Lekki',
        ],
    ];

    public function run(): void
    {
        $john = $this->employee('John Doe', 'EMP001', 'john@euro-mega.com');
        $amaka = $this->employee('Amaka Obi', 'EMP002', 'amaka@euro-mega.com');

        $this->day($john, daysAgo: 4, in: ['ikeja', '08:07'], out: ['ikeja', '17:12']);
        $this->day($john, daysAgo: 1, in: ['yaba', '08:14'], out: ['victoria_island', '17:32']);
        $this->day($john, daysAgo: 0, in: ['ikeja', '08:03'], out: null);

        $this->day($amaka, daysAgo: 1, in: ['lekki', '08:02'], out: ['ikeja', '16:58']);
        $this->day($amaka, daysAgo: 0, in: ['surulere', '08:21'], out: ['lekki', '17:05']);
    }

    protected function employee(string $name, string $employeeId, string $email): User
    {
        $user = User::query()->firstOrNew(['email' => $email]);

        if ($user->exists) {
            return $user;
        }

        $user->fill([
            'name' => $name,
            'employee_id' => $employeeId,
            'password' => 'password',
            'role' => User::ROLE_EMPLOYEE,
            'email_verified_at' => now(),
        ])->save();

        return $user;
    }

    /**
     * @param  array{0: string, 1: string}  $in
     * @param  array{0: string, 1: string}|null  $out
     */
    protected function day(User $user, int $daysAgo, array $in, ?array $out): void
    {
        $date = today()->subDays($daysAgo);

        $this->record($user, AttendanceRecord::TYPE_CHECK_IN, $date, $in[0], $in[1]);

        if ($out !== null) {
            $this->record($user, AttendanceRecord::TYPE_CHECK_OUT, $date, $out[0], $out[1]);
        }
    }

    protected function record(User $user, string $type, Carbon $date, string $place, string $time): void
    {
        $recordedAt = $date->copy()->setTimeFromTimeString($time);

        if ($recordedAt->greaterThan(now())) {
            return;
        }

        $location = $this->places[$place];

        AttendanceRecord::query()->create([
            'user_id' => $user->id,
            'employee_id' => $user->employee_id,
            'type' => $type,
            'attendance_date' => $date->toDateString(),
            'recorded_at' => $recordedAt,
            'latitude' => $location['latitude'],
            'longitude' => $location['longitude'],
            'accuracy' => random_int(5, 24),
            'address' => $location['address'],
            'city' => $location['city'],
            'state' => 'Lagos',
            'country' => 'Nigeria',
            'device_information' => [
                'model' => 'SM-A556B',
                'platform' => 'Android',
                'platform_version' => '14',
                'browser' => 'Chrome Mobile',
                'language' => 'en-NG',
                'timezone' => 'Africa/Lagos',
                'screen' => '412x915',
            ],
            'user_agent' => 'Mozilla/5.0 (Linux; Android 14; SM-A556B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome Mobile Safari/537.36',
            'ip_address' => '127.0.0.1',
        ]);
    }
}
