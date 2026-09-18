<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAttendanceRequest;
use App\Models\AttendanceRecord;
use App\Services\ReverseGeocoder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceController extends Controller
{
    public function __construct(protected ReverseGeocoder $geocoder) {}

    /**
     * The employee attendance screen.
     */
    public function index(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if ($user->isAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        return view('employee.dashboard', [
            'user' => $user,
            'summary' => $user->dailySummary(),
            'today' => $user->attendanceOn(),
            'geocodeUrl' => route('attendance.geocode'),
            'storeUrl' => route('attendance.store'),
            'serverTime' => now()->toIso8601String(),
        ]);
    }

    /**
     * Reverse geocode a set of coordinates for the live check-in preview.
     */
    public function geocode(Request $request): JsonResponse
    {
        $data = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $location = $this->geocoder->lookup($data['latitude'], $data['longitude']);

        return response()->json([
            'address' => $location['address'],
            'city' => $location['city'],
            'state' => $location['state'],
            'country' => $location['country'],
            'label' => $this->geocoder->label($data['latitude'], $data['longitude']),
        ]);
    }

    /**
     * Store a check in / check out event.
     */
    public function store(StoreAttendanceRequest $request): JsonResponse
    {
        $user = $request->user();
        $type = AttendanceRecord::TYPE_CHECKPOINT;

        $now = now();
        $location = $this->geocoder->lookup($request->input('latitude'), $request->input('longitude'));

        $record = $user->attendances()->create([
            'employee_id' => $user->employee_id,
            'type' => $type,
            'attendance_date' => $now->toDateString(),
            'recorded_at' => $now,
            'latitude' => $request->input('latitude'),
            'longitude' => $request->input('longitude'),
            'accuracy' => $request->input('accuracy'),
            'address' => $location['address'],
            'city' => $location['city'],
            'state' => $location['state'],
            'country' => $location['country'],
            'watermarked_photo' => $this->storePhoto($request, 'watermarked_photo', $user->id, $type, $now->toDateString(), 'watermarked'),
            'device_information' => $this->deviceInformation($request),
            'user_agent' => $request->userAgent(),
            'ip_address' => $request->ip(),
        ]);

        $summary = $user->dailySummary();

        return response()->json([
            'message' => 'Checkpoint recorded successfully.',
            'record' => [
                'id' => $record->id,
                'type' => $record->type,
                'type_label' => strtoupper($record->typeLabel()),
                'time' => $record->recorded_at->format('h:i:s A'),
                'date' => $record->recorded_at->format('d M Y'),
                'location' => $record->locationLabel(),
                'address' => $record->address,
                'accuracy' => $record->accuracyLabel(),
                'device' => $record->deviceLabel(),
                'photo_url' => $record->photoUrl(),
                'original_photo_url' => $record->photoUrl('original'),
            ],
            'status' => $summary['status'],
            'next_type' => AttendanceRecord::TYPE_CHECKPOINT,
            'history_url' => route('attendance.history'),
        ], 201);
    }

    /**
     * Attendance history for the signed in employee.
     */
    public function history(Request $request): View
    {
        $user = $request->user();

        $records = $user->attendances()
            ->orderByDesc('recorded_at')
            ->paginate(30);

        return view('employee.history', [
            'user' => $user,
            'records' => $records,
            'days' => $records->getCollection()->groupBy(
                fn (AttendanceRecord $record) => $record->attendance_date->toDateString()
            ),
        ]);
    }

    /**
     * Stream a stored attendance photo to its owner or an administrator.
     */
    public function photo(Request $request, AttendanceRecord $record, string $variant = 'watermarked'): StreamedResponse
    {
        $user = $request->user();

        if (! $user->isAdmin() && $record->user_id !== $user->id) {
            abort(403);
        }

        $path = $variant === 'original' ? ($record->photo ?: $record->watermarked_photo) : $record->watermarked_photo;

        abort_if(! $path || ! Storage::disk('local')->exists($path), 404, 'Attendance photo not found.');

        return Storage::disk('local')->response($path, null, [
            'Cache-Control' => 'private, max-age=86400',
        ], 'inline');
    }

    /**
     * Store an uploaded attendance photo and return its relative path.
     */
    protected function storePhoto(Request $request, string $field, int $userId, string $type, string $date, string $suffix): ?string
    {
        $file = $request->file($field);

        if (! $file) {
            return null;
        }

        $name = sprintf(
            '%s-%s-%s.%s',
            Str::slug($type, '-'),
            now()->format('His'),
            Str::lower(Str::random(8)),
            $file->getClientOriginalExtension() ?: $file->extension() ?: 'jpg'
        );

        return $file->storeAs("attendance/{$userId}/{$date}", "{$suffix}-{$name}", 'local');
    }

    /**
     * Everything the browser was able to report about the device.
     *
     * @return array<string, mixed>|null
     */
    protected function deviceInformation(Request $request): ?array
    {
        $raw = $request->input('device_information');

        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }

        $decoded = json_decode($raw, true);

        if (! is_array($decoded)) {
            return null;
        }

        return array_filter($decoded, fn ($value) => $value !== null && $value !== '' && $value !== []);
    }
}
