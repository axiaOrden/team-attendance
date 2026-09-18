<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.attendance_api.token' => 'sync-secret-token']);
    }

    public function test_transaction_endpoints_require_the_configured_api_token(): void
    {
        $this->getJson('/api/transactions')->assertUnauthorized();
        $this->withHeader('Authorization', 'Bearer wrong-token')
            ->getJson('/api/transactions')
            ->assertUnauthorized();

        config(['services.attendance_api.token' => null]);

        $this->withHeader('X-API-Key', 'sync-secret-token')
            ->getJson('/api/transactions')
            ->assertUnauthorized();
    }

    public function test_all_transactions_are_returned_with_derived_daily_types(): void
    {
        $employee = User::factory()->create([
            'name' => 'API Employee',
            'employee_id' => 'EMP-API-1',
        ]);

        $this->createTransaction($employee, '2026-09-18 08:00:00');
        $this->createTransaction($employee, '2026-09-18 12:00:00');
        $this->createTransaction($employee, '2026-09-18 17:00:00');

        $response = $this->withHeader('Authorization', 'Bearer sync-secret-token')
            ->getJson('/api/transactions')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.employee_id', 'EMP-API-1')
            ->assertJsonPath('data.0.employee_name', 'API Employee')
            ->assertJsonPath('data.0.transaction_type', 'check-in')
            ->assertJsonPath('data.1.transaction_type', 'checkpoint')
            ->assertJsonPath('data.2.transaction_type', 'check-out')
            ->assertJsonPath('data.0.device_model', 'SM-A556B');

        $this->assertSame(3, $response->json('meta.total'));
    }

    public function test_employee_endpoint_and_date_range_filter_transactions(): void
    {
        $employee = User::factory()->create(['employee_id' => 'EMP100']);
        $otherEmployee = User::factory()->create(['employee_id' => 'EMP200']);

        $this->createTransaction($employee, '2026-09-01 08:00:00');
        $expected = $this->createTransaction($employee, '2026-09-18 08:00:00');
        $this->createTransaction($otherEmployee, '2026-09-18 09:00:00');

        $this->withHeader('X-API-Key', 'sync-secret-token')
            ->getJson('/api/transactions/emp100?from=2026-09-18&to=2026-09-18')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.transaction_id', $expected->id)
            ->assertJsonPath('data.0.transaction_type', 'check-in');
    }

    public function test_api_validates_dates_and_does_not_expose_write_routes(): void
    {
        $headers = ['Authorization' => 'Bearer sync-secret-token'];

        $this->withHeaders($headers)
            ->getJson('/api/transactions?from=2026-09-18&to=2026-09-01')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('to');

        $this->withHeaders($headers)
            ->postJson('/api/transactions', [])
            ->assertMethodNotAllowed();
    }

    protected function createTransaction(User $employee, string $recordedAt): AttendanceRecord
    {
        return AttendanceRecord::query()->create([
            'user_id' => $employee->id,
            'employee_id' => $employee->employee_id,
            'type' => AttendanceRecord::TYPE_CHECKPOINT,
            'attendance_date' => substr($recordedAt, 0, 10),
            'recorded_at' => $recordedAt,
            'latitude' => 6.6018,
            'longitude' => 3.3515,
            'accuracy' => 8,
            'address' => 'Akanni Doherty Street, Ikeja',
            'city' => 'Ikeja',
            'state' => 'Lagos',
            'country' => 'Nigeria',
            'device_information' => [
                'model' => 'SM-A556B',
                'platform' => 'Android',
            ],
        ]);
    }
}
