<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
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
            'type' => AttendanceRecord::TYPE_CHECKPOINT,
            'latitude' => '6.6018000',
            'longitude' => '3.3515000',
            'accuracy' => '8',
            'device_information' => json_encode([
                'model' => 'SM-A556B',
                'platform' => 'Android',
                'platform_version' => '14',
                'browser' => 'Chrome 139',
            ]),
            'watermarked_photo' => UploadedFile::fake()->image('attendance-watermarked.jpg'),
        ], $overrides);
    }

    public function test_employees_can_submit_unlimited_checkpoints(): void
    {
        $employee = User::factory()->create(['employee_id' => 'EMP555']);

        $this->actingAs($employee)
            ->post(route('attendance.store'), $this->payload())
            ->assertCreated()
            ->assertJsonPath('record.type', AttendanceRecord::TYPE_CHECKPOINT)
            ->assertJsonPath('status', 'active')
            ->assertJsonPath('next_type', AttendanceRecord::TYPE_CHECKPOINT);

        $record = AttendanceRecord::query()->sole();

        $this->assertSame('EMP555', $record->employee_id);
        $this->assertEqualsWithDelta(6.6018, $record->latitude, 0.000001);
        $this->assertEqualsWithDelta(3.3515, $record->longitude, 0.000001);
        $this->assertSame('6.601800, 3.351500', $record->coordinateLabel());
        $this->assertSame('SM-A556B · Android 14', $record->deviceLabel());
        $this->assertNull($record->photo);
        $this->assertNotNull($record->watermarked_photo);
        Storage::disk('local')->assertExists($record->watermarked_photo);

        $this->actingAs($employee)
            ->post(route('attendance.store'), $this->payload())
            ->assertCreated();

        $this->actingAs($employee)
            ->post(route('attendance.store'), $this->payload([
                'type' => AttendanceRecord::TYPE_CHECKPOINT,
            ]))
            ->assertCreated()
            ->assertJsonPath('record.type', AttendanceRecord::TYPE_CHECKPOINT)
            ->assertJsonPath('status', 'active');

        $this->actingAs($employee)
            ->post(route('attendance.store'), $this->payload([
                'type' => AttendanceRecord::TYPE_CHECKPOINT,
            ]))
            ->assertCreated();

        $this->assertSame(4, AttendanceRecord::query()->count());
        $this->assertSame([AttendanceRecord::TYPE_CHECKPOINT], AttendanceRecord::query()->distinct()->pluck('type')->all());

        $this->actingAs($employee)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('ADD CHECKPOINT')
            ->assertSee('4 checkpoints');
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
                'watermarked_photo' => null,
            ]))
            ->assertSessionHasErrors(['watermarked_photo']);

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

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->assertSee('Employee daily attendance');
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
            ->get(route('admin.dashboard', ['employee_code' => 'EMP020', 'date' => today()->subDay()->toDateString()]))
            ->assertOk();

        $this->assertSame(['EMP020'], array_column($this->mapPoints($codeFiltered), 'employee_id'));

        $employeeFiltered = $this->actingAs($admin)
            ->get(route('admin.dashboard', ['employee' => $john->id]))
            ->assertOk();

        $this->assertSame(['EMP010'], array_column($this->mapPoints($employeeFiltered), 'employee_id'));
    }

    public function test_dashboard_summarizes_daily_attendance_and_exports_filtered_csv(): void
    {
        $admin = User::factory()->admin()->create();
        $employee = User::factory()->create(['name' => 'Daily Summary', 'employee_id' => 'EMP777']);
        $date = today()->toDateString();

        foreach ([
            [AttendanceRecord::TYPE_CHECKPOINT, '08:00:00'],
            [AttendanceRecord::TYPE_CHECKPOINT, '10:30:00'],
            [AttendanceRecord::TYPE_CHECKPOINT, '14:15:00'],
            [AttendanceRecord::TYPE_CHECKPOINT, '17:00:00'],
        ] as [$type, $time]) {
            AttendanceRecord::query()->create([
                'user_id' => $employee->id,
                'employee_id' => $employee->employee_id,
                'type' => $type,
                'attendance_date' => $date,
                'recorded_at' => $date.' '.$time,
                'latitude' => 6.6,
                'longitude' => 3.35,
            ]);
        }

        $this->actingAs($admin)
            ->get(route('admin.dashboard', ['date' => $date]))
            ->assertOk()
            ->assertSee('Daily Summary')
            ->assertSee('09:00')
            ->assertSee('>4<', false);

        $mapPoint = $this->mapPoints($this->actingAs($admin)->get(route('admin.dashboard', ['date' => $date])))[0];
        $this->assertSame('05:00 PM', $mapPoint['time_long']);
        $this->assertSame(4, $mapPoint['checkpoint_total']);

        $export = $this->actingAs($admin)->get(route('admin.dashboard.export', [
            'date' => $date,
            'employee' => $employee->id,
        ]));

        $export->assertOk()->assertDownload();
        $csv = $export->streamedContent();

        $this->assertStringContainsString('EMP777,"Daily Summary"', $csv);
        $this->assertStringContainsString('09:00,4', $csv);
    }

    public function test_deactivated_employees_are_excluded_and_cannot_submit_checkpoints(): void
    {
        $admin = User::factory()->admin()->create();
        $activeEmployee = User::factory()->create(['name' => 'Active Employee', 'employee_id' => 'ACTIVE01']);
        $inactiveEmployee = User::factory()->create(['name' => 'Former Employee', 'employee_id' => 'FORMER01']);

        $this->actingAs($activeEmployee)->post(route('attendance.store'), $this->payload())->assertCreated();
        $this->actingAs($inactiveEmployee)->post(route('attendance.store'), $this->payload())->assertCreated();

        $this->actingAs($admin)
            ->patch(route('admin.employees.status', $inactiveEmployee), ['is_inactive' => true])
            ->assertRedirect();

        $this->assertTrue($inactiveEmployee->refresh()->is_inactive);
        $this->assertSame(2, AttendanceRecord::query()->count());

        $dashboard = $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
        $this->assertSame(['ACTIVE01'], array_column($this->mapPoints($dashboard), 'employee_id'));
        $dashboard->assertSee('1 latest employee locations');

        $export = $this->actingAs($admin)->get(route('admin.dashboard.export'));
        $this->assertStringNotContainsString('FORMER01', $export->streamedContent());

        $this->actingAs($inactiveEmployee)
            ->post(route('attendance.store'), $this->payload())
            ->assertForbidden();

        $this->actingAs($admin)
            ->patch(route('admin.employees.status', $inactiveEmployee), ['is_inactive' => false])
            ->assertRedirect();

        $this->assertNull($inactiveEmployee->refresh()->is_inactive);

        $this->actingAs($inactiveEmployee)
            ->post(route('attendance.store'), $this->payload())
            ->assertCreated();
    }

    /**
     * The map data only contains the filtered records, which makes it a precise
     * way to assert what the administrator actually sees.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function mapPoints(TestResponse $response): array
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
