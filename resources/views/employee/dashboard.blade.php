@php
    $hour = (int) now()->format('G');
    $greeting = $hour < 12 ? 'Good Morning' : ($hour < 17 ? 'Good Afternoon' : 'Good Evening');

    $status = $summary['status'];
    $nextType = \App\Models\AttendanceRecord::TYPE_CHECKPOINT;

    [$statusLabel, $statusVariant, $statusIcon] = match ($status) {
        'active' => [$summary['checkpoints'].' '.\Illuminate\Support\Str::plural('checkpoint', $summary['checkpoints']), 'success', 'add_location_alt'],
        default => ['No checkpoints yet', 'warning', 'schedule'],
    };

    $steps = [
        ['key' => 'location', 'label' => 'GPS location', 'icon' => 'my_location'],
        ['key' => 'address', 'label' => 'Address lookup', 'icon' => 'location_on'],
        ['key' => 'photo', 'label' => 'Attendance photo', 'icon' => 'photo_camera'],
        ['key' => 'save', 'label' => 'Save record', 'icon' => 'check_circle'],
    ];
@endphp

<x-app-layout :title="'Attendance'">
    <div class="stack-md"
         data-attendance-app
         data-store-url="{{ route('attendance.store') }}"
         data-geocode-url="{{ route('attendance.geocode') }}"
         data-history-url="{{ route('attendance.history') }}"
         data-employee-name="{{ $user->name }}"
         data-employee-id="{{ $user->employee_id }}"
         data-timezone="{{ config('app.timezone') }}"
         data-server-time="{{ $serverTime }}">

        {{-- Greeting and live clock --}}
        <section class="m3-card">
            <div class="md-label">{{ $greeting }}</div>
            <h1 class="md-headline" style="margin:2px 0 10px">{{ $user->name }}</h1>

            <div class="row-wrap">
                <span class="m3-chip m3-chip--outlined">
                    <x-md-icon name="account_circle" size="sm" />
                    <span>{{ $user->employee_id }}</span>
                </span>
                <span class="m3-chip m3-chip--outlined">
                    <x-md-icon name="calendar_today" size="sm" />
                    <span data-live-date>{{ now()->isoFormat('dddd, D MMMM') }}</span>
                </span>
            </div>

            <div class="md-mono md-headline" style="margin-top:14px" data-live-clock>{{ now()->format('H:i:s') }}</div>
            <div class="m3-help">Server time ({{ config('app.timezone') }}) - used for every attendance record.</div>
        </section>

        {{-- Big attendance action --}}
        <section class="m3-card" style="display:flex;flex-direction:column;gap:18px">
            <div class="row-between">
                <span class="md-label">Today's attendance</span>
                <span class="m3-chip m3-chip--{{ $statusVariant }}" data-status-chip>
                    {{ $statusLabel }}
                </span>
            </div>

            <button type="button"
                    class="m3-action"
                    data-attendance-button
                    data-type="{{ $nextType }}"
                    >
                <span class="m3-pulse" aria-hidden="true"></span>
                <x-md-icon name="add_location_alt" size="lg" />
                <span data-attendance-button-label>ADD CHECKPOINT</span>
                <span class="m3-action__hint">
                    Tap to record your current location
                </span>
            </button>

            <div class="row-wrap" style="justify-content:center">
                <span class="m3-chip m3-chip--outlined">
                    <x-md-icon name="my_location" size="sm" />
                    <span data-accuracy>GPS ready</span>
                </span>
                <span class="m3-chip m3-chip--outlined">
                    <x-md-icon name="location_on" size="sm" />
                    <span data-location>{{ $summary['check_in']?->locationLabel() ?? 'Location on capture' }}</span>
                </span>
            </div>

            <p class="m3-help" style="text-align:center">
                Your GPS position and a fresh camera photo are captured when you tap the button.
                Coordinates cannot be entered or edited manually.
            </p>
        </section>

        {{-- Progress while capturing --}}
        <section class="m3-card is-hidden" data-progress-panel>
            <div class="m3-locating">
                <div class="m3-locating__pin">
                    <x-md-icon name="my_location" size="lg" />
                </div>
                <div>
                    <div class="m3-row__title">Recording attendance</div>
                    <div class="md-muted">Keep this screen open until it finishes.</div>
                </div>
            </div>

            <div class="m3-progress-track"><span></span></div>
            <div class="m3-divider"></div>

            <div class="m3-list">
                @foreach ($steps as $step)
                    <div class="m3-row" data-step="{{ $step['key'] }}" data-state="pending">
                        <div class="m3-row__icon">
                            <span data-step-icon>○</span>
                        </div>
                        <div class="m3-row__body">
                            <div class="m3-row__title">{{ $step['label'] }}</div>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- Success --}}
        <section class="m3-card is-hidden" data-success-card>
            <div class="m3-success-badge">
                <x-md-icon name="check_circle" size="lg" />
            </div>

            <div style="text-align:center;margin-top:14px">
                <div class="md-label" data-success-type>CHECKED IN</div>
                <div class="md-mono" style="font-size:1.5rem;font-weight:600" data-success-time></div>
            </div>

            <div class="m3-divider"></div>

            <div class="m3-facts">
                <div class="m3-fact">
                    <span class="m3-fact__label">Location</span>
                    <span class="m3-fact__value" data-success-location></span>
                </div>
                <div class="m3-fact">
                    <span class="m3-fact__label">GPS accuracy</span>
                    <span class="m3-fact__value" data-success-accuracy></span>
                </div>
                <div class="m3-fact">
                    <span class="m3-fact__label">Photo</span>
                    <span class="m3-fact__value">Captured with watermark</span>
                </div>
            </div>

            <img class="m3-photo is-hidden" style="margin-top:14px" data-success-image alt="Attendance photo">

            <div class="row-wrap" style="margin-top:14px">
                <a class="m3-btn m3-btn--tonal m3-btn--sm" data-success-photo href="#" target="_blank" rel="noopener">
                    <x-md-icon name="image" size="sm" />
                    <span>View photo</span>
                </a>
                <a class="m3-btn m3-btn--text m3-btn--sm" href="{{ route('attendance.history') }}">
                    <x-md-icon name="history" size="sm" />
                    <span>View Attendance</span>
                </a>
            </div>
        </section>

        {{-- Today's events --}}
        <section class="m3-card">
            <div class="m3-card__header">
                <span class="m3-card__title">Today's events</span>
                <a class="m3-btn m3-btn--text m3-btn--sm" href="{{ route('attendance.history') }}">
                    <span>History</span>
                    <x-md-icon name="arrow_back" size="sm" class="m3-flip" />
                </a>
            </div>

            <div class="m3-list" data-today-list>
                @forelse ($today as $record)
                    <div class="m3-row">
                        <div class="m3-row__icon">
                            •
                        </div>
                        <div class="m3-row__body">
                            <div class="m3-row__title">
                                {{ strtoupper($record->typeLabel()) }} · {{ $record->recorded_at->format('h:i:s A') }}
                            </div>
                            <div class="m3-row__meta">
                                <x-md-icon name="location_on" size="sm" />
                                <span>{{ $record->locationLabel() }}</span>
                                <span class="md-muted">· ±{{ $record->accuracyLabel() }}</span>
                            </div>
                            @if ($record->photoUrl())
                                <a class="m3-chip m3-chip--outlined" style="margin-top:8px" href="{{ $record->photoUrl() }}" target="_blank" rel="noopener">
                                    <x-md-icon name="photo_camera" size="sm" />
                                    <span>View Photo</span>
                                </a>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="m3-row" data-today-empty>
                        <div class="m3-row__icon">
                            <x-md-icon name="schedule" size="sm" />
                        </div>
                        <div class="m3-row__body">
                            <div class="m3-row__title">No attendance yet today</div>
                            <div class="m3-row__meta">Your check in will appear here.</div>
                        </div>
                    </div>
                @endforelse
            </div>
        </section>

        {{-- Camera sheet --}}
        <div class="m3-scrim" data-camera-scrim></div>

        <div class="m3-sheet" data-camera-sheet>
            <div class="m3-sheet__handle"></div>
            <div class="m3-sheet__title">Attendance photo</div>
            <p class="m3-help" style="margin-bottom:12px">
                Take a clear photo of yourself. The photo is watermarked with your name, employee ID,
                time, coordinates, address and device before it is uploaded.
            </p>

            <div class="m3-camera">
                <video data-camera-video playsinline autoplay muted></video>
                <img class="m3-photo is-hidden" data-camera-preview-image alt="Attendance photo preview">
                <div class="m3-camera__hint" data-camera-hint>Hold the phone at eye level</div>
            </div>

            <div class="row-wrap" style="justify-content:center;margin-top:16px">
                <button type="button" class="m3-capture" data-capture-button aria-label="Take photo">
                    <span class="m3-capture__core"></span>
                </button>
                <button type="button" class="m3-btn m3-btn--tonal m3-btn--sm is-hidden" data-switch-camera-button>
                    Use back camera
                </button>
            </div>

            <div class="is-hidden" data-camera-fallback>
                <label class="m3-btn m3-btn--tonal m3-btn--block" for="attendance-photo-input">
                    <x-md-icon name="photo_camera" size="sm" />
                    <span>Open camera</span>
                </label>
                <input id="attendance-photo-input"
                       class="is-hidden"
                       type="file"
                       accept="image/*"
                       capture="user"
                       data-camera-fallback-input>
                <p class="m3-help" style="text-align:center;margin-top:8px">
                    This browser cannot show a live camera preview (a secure HTTPS connection is required),
                    so the camera app will open instead.
                </p>
            </div>

            <div class="row-wrap" style="margin-top:12px">
                <button type="button" class="m3-btn m3-btn--outlined is-hidden" data-retake-button>
                    <x-md-icon name="refresh" size="sm" />
                    <span>Retake</span>
                </button>
                <button type="button" class="m3-btn m3-btn--filled is-hidden" style="flex:1" data-submit-button>
                    <x-md-icon name="check_circle" size="sm" />
                    <span>Submit attendance</span>
                </button>
            </div>
        </div>

        <div class="m3-snackbar" data-snackbar role="status"></div>
    </div>
</x-app-layout>
