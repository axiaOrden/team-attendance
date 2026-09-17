<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AttendanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    /**
     * @return array<string, mixed>
     */
    protected function payload(array $overrides = []): array
    {
        return array_merge([
            'type' => AttendanceRecord::TYPE_CHECK_IN,
            'latitude' => '6.6018000',
            'longitude' => '3.3515000',
            'accuracy' => '8',
            'device_information' => json_encode([
                'model' => 'SM-A556B',
                'platform' => 'Android',
                'platform_version' => '14',
                'browser' => 'Chrome 139',
            ]),
            'photo' => UploadedFile::fake()->image('attendance.jpg'),
            'watermarked_photo' => UploadedFile::fake()->image('attendance-watermarked.jpg'),
        ], $overrides);
    }

    public function test_employees_can_check_in_and_check_out(): void
    {
        $employee = User::factory()->create(['employee_id' => 'EMP555']);

        $this->actingAs($employee)
            ->post(route('attendance.store'), $this->payload())
            ->assertCreated()
            ->assertJsonPath('record.type', AttendanceRecord::TYPE_CHECK_IN)
            ->assertJsonPath('status', 'checked_in')
            ->assertJsonPath('next_type', AttendanceRecord::TYPE_CHECK_OUT);

        $record = AttendanceRecord::query()->sole();

        $this->assertSame('EMP555', $record->employee_id);
        $this->assertEqualsWithDelta(6.6018, $record->latitude, 0.000001);
        $this->assertEqualsWithDelta(3.3515, $record->longitude, 0.000001);
        $this->assertSame('6.601800, 3.351500', $record->coordinateLabel());
        $this->assertSame('SM-A556B · Android 14', $record->deviceLabel());
        $this->assertNotNull($record->photo);
        $this->assertNotNull($record->watermarked_photo);
        Storage::disk('local')->assertExists($record->photo);
        Storage::disk('local')->assertExists($record->watermarked_photo);

        // A second check in on the same day is refused.
        $this->actingAs($employee)
            ->post(route('attendance.store'), $this->payload())
            ->assertStatus(409);

        $this->actingAs($employee)
            ->post(route('attendance.store'), $this->payload([
                'type' => AttendanceRecord::TYPE_CHECK_OUT,
                'accuracy' => '12',
            ]))
            ->assertCreated()
            ->assertJsonPath('status', 'checked_out');

        $this->assertSame(2, AttendanceRecord::query()->count());
    }

    public function test_gps_coordinates_are_required(): void
    {
        $employee = User::factory()->create();

        $this->actingAs($employee)
            ->post(route('attendance.store'), $this->payload([
                'latitude' => null,
                'longitude' => null,
            ]))
            ->assertSessionHasErrors(['latitude', 'longitude']);

        $this->assertSame(0, AttendanceRecord::query()->count());
    }

    public function test_a_photo_is_required(): void
    {
        $employee = User::factory()->create();

        $this->actingAs($employee)
            ->post(route('attendance.store'), $this->payload([
                'photo' => null,
                'watermarked_photo' => null,
            ]))
            ->assertSessionHasErrors(['photo', 'watermarked_photo']);

        $this->assertSame(0, AttendanceRecord::query()->count());
    }

    public function test_attendance_photos_are_private(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $admin = User::factory()->admin()->create();

        $this->actingAs($owner)->post(route('attendance.store'), $this->payload())->assertCreated();

        $record = AttendanceRecord::query()->sole();

        $this->actingAs($owner)->get(route('attendance.photo', $record))->assertOk();
        $this->actingAs($admin)->get(route('attendance.photo', $record))->assertOk();
        $this->actingAs($admin)->get(route('attendance.photo', [$record, 'original']))->assertOk();
        $this->actingAs($other)->get(route('attendance.photo', $record))->assertForbidden();
    }

    public function test_employees_only_see_their_own_history(): void
    {
        $employee = User::factory()->create(['name' => 'Jane Employee']);
        $colleague = User::factory()->create(['name' => 'Other Person']);

        $this->actingAs($employee)->post(route('attendance.store'), $this->payload())->assertCreated();
        $this->actingAs($colleague)->post(route('attendance.store'), $this->payload())->assertCreated();

        $this->actingAs($employee)
            ->get(route('attendance.history'))
            ->assertOk()
            ->assertSee('Jane Employee')
            ->assertDontSee('Other Person');
    }

    public function test_administrators_see_the_dashboard_and_trail_and_employees_do_not(): void
    {
        $admin = User::factory()->admin()->create();
        $employee = User::factory()->create();

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->assertSee('Attendance records');
        $this->actingAs($admin)->get(route('admin.trail'))->assertOk()->assertSee('Employee trail map');
        $this->actingAs($admin)->get(route('admin.employees'))->assertOk();

        $this->actingAs($employee)->get(route('admin.dashboard'))->assertForbidden();
        $this->actingAs($employee)->get(route('admin.trail'))->assertForbidden();
        $this->actingAs($employee)->get(route('admin.employees'))->assertForbidden();
    }

    public function test_administrators_can_filter_attendance_records(): void
    {
        $admin = User::factory()->admin()->create();
        $john = User::factory()->create(['name' => 'John Filter', 'employee_id' => 'EMP010']);
        $mary = User::factory()->create(['name' => 'Mary Filter', 'employee_id' => 'EMP020']);

        AttendanceRecord::query()->create([
            'user_id' => $john->id,
            'employee_id' => $john->employee_id,
            'type' => AttendanceRecord::TYPE_CHECK_IN,
            'attendance_date' => today()->toDateString(),
            'recorded_at' => now(),
            'latitude' => 6.6,
            'longitude' => 3.35,
            'accuracy' => 10,
        ]);

        AttendanceRecord::query()->create([
            'user_id' => $mary->id,
            'employee_id' => $mary->employee_id,
            'type' => AttendanceRecord::TYPE_CHECK_OUT,
            'attendance_date' => today()->subDay()->toDateString(),
            'recorded_at' => now()->subDay(),
            'latitude' => 6.42,
            'longitude' => 3.42,
            'accuracy' => 14,
        ]);

        $typeFiltered = $this->actingAs($admin)
            ->get(route('admin.dashboard', ['type' => 'check_in']))
            ->assertOk();

        $this->assertSame(['EMP010'], array_column($this->mapPoints($typeFiltered), 'employee_id'));

        $dateFiltered = $this->actingAs($admin)
            ->get(route('admin.dashboard', ['date' => today()->toDateString()]))
            ->assertOk();

        $this->assertSame(['EMP010'], array_column($this->mapPoints($dateFiltered), 'employee_id'));

        $rangeFiltered = $this->actingAs($admin)
            ->get(route('admin.dashboard', [
                'from' => today()->subDay()->toDateString(),
                'to' => today()->subDay()->toDateString(),
            ]))
            ->assertOk();

        $this->assertSame(['EMP020'], array_column($this->mapPoints($rangeFiltered), 'employee_id'));

        $codeFiltered = $this->actingAs($admin)
            ->get(route('admin.dashboard', ['employee_code' => 'EMP020']))
            ->assertOk();

        $this->assertSame(['EMP020'], array_column($this->mapPoints($codeFiltered), 'employee_id'));

        $employeeFiltered = $this->actingAs($admin)
            ->get(route('admin.dashboard', ['employee' => $john->id]))
            ->assertOk();

        $this->assertSame(['EMP010'], array_column($this->mapPoints($employeeFiltered), 'employee_id'));
    }

    /**
     * The map data only contains the filtered records, which makes it a precise
     * way to assert what the administrator actually sees.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function mapPoints(\Illuminate\Testing\TestResponse $response): array
    {
        preg_match('#<script type="application/json" id="admin-map-data">(.*?)</script>#s', $response->getContent(), $matches);

        return json_decode($matches[1] ?? '[]', true) ?: [];
    }

    public function test_employees_cannot_record_attendance_for_other_people(): void
    {
        $employee = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($employee)->post(route('attendance.store'), $this->payload([
            'user_id' => $other->id,
        ]))->assertCreated();

        $this->assertSame($employee->id, AttendanceRecord::query()->sole()->user_id);
    }
}
