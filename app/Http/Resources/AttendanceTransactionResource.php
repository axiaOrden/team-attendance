<?php

namespace App\Http\Resources;

use App\Models\AttendanceRecord;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceTransactionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'employee_id' => $this->employee_id,
            'employee_name' => $this->user?->name,
            'transaction_id' => $this->id,
            'timestamp' => $this->recorded_at->toIso8601String(),
            'transaction_type' => $this->transactionType(),
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'gps_accuracy' => $this->accuracy,
            'address' => $this->address,
            'city' => $this->city,
            'state' => $this->state,
            'country' => $this->country,
            'device_model' => $this->device_information['model'] ?? null,
        ];
    }

    protected function transactionType(): string
    {
        if ($this->id === (int) $this->day_first_id) {
            return 'check-in';
        }

        if ($this->id === (int) $this->day_last_id) {
            return 'check-out';
        }

        return AttendanceRecord::TYPE_CHECKPOINT;
    }
}
