@extends('layouts.admin')

@section('title', 'Attendance dashboard')

@php
    $activeFilters = collect($filters)->filter(fn ($value) => $value !== null && $value !== '')->count();
@endphp

@section('content')
    <div class="admin-grid">
        <div class="stat-card">
            <div class="stat-card__label">Records</div>
            <div class="stat-card__value">{{ number_format($stats['records']) }}</div>
        </div>
        <div class="stat-card">
            <div class="stat-card__label">Check ins</div>
            <div class="stat-card__value text-success">{{ number_format($stats['check_ins']) }}</div>
        </div>
        <div class="stat-card">
            <div class="stat-card__label">Check outs</div>
            <div class="stat-card__value">{{ number_format($stats['check_outs']) }}</div>
        </div>
        <div class="stat-card">
            <div class="stat-card__label">Employees</div>
            <div class="stat-card__value">{{ number_format($stats['employees']) }}</div>
        </div>
    </div>

    <form class="m3-card m3-card--flat" method="GET" action="{{ route('admin.dashboard') }}">
        <div class="m3-card__header">
            <span class="m3-card__title">Filters</span>
            <span class="md-muted" style="font-size:.75rem">{{ $activeFilters }} active</span>
        </div>

        <div class="filter-grid">
            <div class="m3-field">
                <label class="m3-field__label" for="filter-date">Date</label>
                <input class="m3-input" type="date" id="filter-date" name="date" value="{{ $filters['date'] }}">
            </div>

            <div class="m3-field">
                <label class="m3-field__label" for="filter-from">Date range from</label>
                <input class="m3-input" type="date" id="filter-from" name="from" value="{{ $filters['from'] }}">
            </div>

            <div class="m3-field">
                <label class="m3-field__label" for="filter-to">Date range to</label>
                <input class="m3-input" type="date" id="filter-to" name="to" value="{{ $filters['to'] }}">
            </div>

            <div class="m3-field">
                <label class="m3-field__label" for="filter-employee">Employee</label>
                <select class="m3-select" id="filter-employee" name="employee" data-auto-submit>
                    <option value="">All employees</option>
                    @foreach ($employees as $person)
                        <option value="{{ $person->id }}" @selected((int) $filters['employee'] === $person->id)>
                            {{ $person->name }} ({{ $person->employee_id }})
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="m3-field">
                <label class="m3-field__label" for="filter-code">Employee ID</label>
                <input class="m3-input" type="text" id="filter-code" name="employee_code"
                       value="{{ $filters['employee_code'] }}" placeholder="EMP001" autocomplete="off">
            </div>

            <div class="m3-field">
                <label class="m3-field__label" for="filter-type">Check in / Check out</label>
                <select class="m3-select" id="filter-type" name="type" data-auto-submit>
                    <option value="">Check in and check out</option>
                    <option value="check_in" @selected($filters['type'] === 'check_in')>Check in only</option>
                    <option value="check_out" @selected($filters['type'] === 'check_out')>Check out only</option>
                </select>
            </div>
        </div>

        <div class="row-wrap" style="margin-top:14px">
            <button type="submit" class="m3-btn m3-btn--filled m3-btn--sm">
                <x-md-icon name="tune" size="sm" />
                <span>Apply filters</span>
            </button>
            <a class="m3-btn m3-btn--text m3-btn--sm" href="{{ route('admin.dashboard') }}">
                <x-md-icon name="refresh" size="sm" />
                <span>Reset</span>
            </a>
        </div>
    </form>

    <div class="row-between">
        <span class="m3-card__title">Attendance records</span>
        <div class="segment segment--view" role="tablist" aria-label="View mode">
            <button type="button" class="segment__btn" data-view-target="list" aria-selected="true">
                <x-md-icon name="history" size="sm" />
                <span>List</span>
            </button>
            <button type="button" class="segment__btn" data-view-target="map" aria-selected="false">
                <x-md-icon name="map" size="sm" />
                <span>Map</span>
            </button>
        </div>
    </div>

    <div class="admin-split">
        <section data-view="list" class="stack-md">
            <div class="m3-card m3-card--flat">
                @if ($records->isEmpty())
                    <div style="text-align:center;padding:12px">
                        <x-md-icon name="history" size="lg" />
                        <div class="m3-row__title" style="margin-top:8px">No attendance records match these filters</div>
                        <p class="m3-help">Try a wider date range or reset the filters.</p>
                    </div>
                @else
                    <div class="admin-table-wrap">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Date &amp; time</th>
                                    <th>Employee</th>
                                    <th>Type</th>
                                    <th>Location</th>
                                    <th>Accuracy</th>
                                    <th>Device</th>
                                    <th>Photo</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($records as $record)
                                    <tr>
                                        <td>
                                            {{ $record->recorded_at->format('d M Y') }}
                                            <div class="md-muted">{{ $record->recorded_at->format('h:i A') }}</div>
                                        </td>
                                        <td>
                                            {{ $record->user?->name ?? 'Deleted user' }}
                                            <div class="md-muted">{{ $record->employee_id }}</div>
                                        </td>
                                        <td>
                                            <span class="m3-chip m3-chip--{{ $record->isCheckIn() ? 'success' : 'info' }}">
                                                {{ $record->typeLabel() }}
                                            </span>
                                        </td>
                                        <td>
                                            {{ $record->locationLabel() }}
                                            @if ($record->address)
                                                <div class="md-muted">{{ \Illuminate\Support\Str::limit($record->address, 48) }}</div>
                                            @endif
                                        </td>
                                        <td>{{ $record->accuracyLabel() }}</td>
                                        <td>{{ $record->deviceLabel() }}</td>
                                        <td>
                                            @if ($record->photoUrl())
                                                <button type="button"
                                                        class="m3-chip m3-chip--outlined"
                                                        data-photo-thumb
                                                        data-photo-src="{{ $record->photoUrl() }}"
                                                        data-photo-original="{{ $record->photoUrl('original') }}">
                                                    <x-md-icon name="photo_camera" size="sm" />
                                                    <span>View</span>
                                                </button>
                                            @else
                                                <span class="md-muted">No photo</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="admin-cards">
                        @foreach ($records as $record)
                            <div class="admin-record-card">
                                <div class="m3-row__icon {{ $record->isCheckIn() ? 'm3-row__icon--success' : '' }}">
                                    {{ $record->isCheckIn() ? '↓' : '↑' }}
                                </div>
                                <div class="m3-row__body">
                                    <div class="row-between">
                                        <span class="m3-row__title">{{ $record->user?->name ?? 'Deleted user' }}</span>
                                        <span class="m3-chip m3-chip--{{ $record->isCheckIn() ? 'success' : 'info' }}">
                                            {{ $record->typeLabel() }}
                                        </span>
                                    </div>
                                    <div class="m3-row__meta">
                                        {{ $record->recorded_at->format('d M Y · h:i A') }} · {{ $record->employee_id }}
                                    </div>
                                    <div class="m3-row__meta">
                                        <x-md-icon name="location_on" size="sm" />
                                        <span>{{ $record->locationLabel() }} · ±{{ $record->accuracyLabel() }}</span>
                                    </div>
                                    <div class="m3-row__meta">{{ $record->deviceLabel() }}</div>

                                    @if ($record->photoUrl())
                                        <button type="button"
                                                class="m3-chip m3-chip--outlined"
                                                style="margin-top:8px"
                                                data-photo-thumb
                                                data-photo-src="{{ $record->photoUrl() }}"
                                                data-photo-original="{{ $record->photoUrl('original') }}">
                                            <x-md-icon name="photo_camera" size="sm" />
                                            <span>View photo</span>
                                        </button>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{ $records->links() }}
        </section>

        <section data-view="map" class="stack-md">
            <div class="m3-card m3-card--flat stack-sm">
                <div id="admin-map" class="map"></div>

                <div class="map-legend">
                    <span class="map-legend__item">
                        <span class="map-legend__dot" style="background: var(--md-success)"></span> Check in
                    </span>
                    <span class="map-legend__item">
                        <span class="map-legend__dot" style="background: var(--md-primary)"></span> Check out
                    </span>
                    <span class="map-legend__item">{{ $points->count() }} plotted locations (max 500)</span>
                </div>

                <p class="m3-help">
                    Locations are the GPS coordinates recorded with each attendance event.
                    Tap a marker for the employee, time, accuracy, address, device and photo.
                </p>
            </div>
        </section>
    </div>

    <script type="application/json" id="admin-map-data">@json($points)</script>
@endsection
