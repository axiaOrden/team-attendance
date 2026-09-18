@extends('layouts.admin')

@section('title', 'Attendance dashboard')

@php
    $query = collect($filters)->except('date')->filter(fn ($value) => $value !== null && $value !== '')->all();
    $activeFilters = collect($filters)->only(['employee', 'employee_code', 'status'])->filter()->count();
    $rangeLabel = $filters['from'] === $filters['to']
        ? \Illuminate\Support\Carbon::parse($filters['from'])->format('d M Y')
        : \Illuminate\Support\Carbon::parse($filters['from'])->format('d M Y').' – '.\Illuminate\Support\Carbon::parse($filters['to'])->format('d M Y');
    $calendarMonth = \Illuminate\Support\Carbon::parse(request('month', $filters['from']))->startOfMonth();
    $calendarStart = $calendarMonth->copy()->startOfWeek(\Illuminate\Support\Carbon::SUNDAY);
    $calendarDays = collect(range(0, 41))->map(fn ($offset) => $calendarStart->copy()->addDays($offset));
    $activityDateLookup = $activityDates->flip();
@endphp

@section('content')
    <form class="m3-card dashboard-calendar" method="GET" action="{{ route('admin.dashboard') }}" data-date-filter>
        <div class="row-between">
            <div>
                <div class="md-label">Date / date range</div>
                <div class="md-headline dashboard-calendar__title" data-calendar-label>{{ $rangeLabel }}</div>
            </div>
            <x-md-icon name="calendar_month" size="lg" />
        </div>

        <input type="hidden" name="from" value="{{ $filters['from'] }}" data-calendar-from>
        <input type="hidden" name="to" value="{{ $filters['to'] }}" data-calendar-to>

        <div class="calendar-picker" data-calendar-picker>
            <div class="calendar-picker__header">
                <a class="m3-btn m3-btn--text m3-btn--sm" aria-label="Previous month" href="{{ route('admin.dashboard', array_merge($query, ['month' => $calendarMonth->copy()->subMonth()->format('Y-m')])) }}">‹</a>
                <strong>{{ $calendarMonth->format('F Y') }}</strong>
                <a class="m3-btn m3-btn--text m3-btn--sm" aria-label="Next month" href="{{ route('admin.dashboard', array_merge($query, ['month' => $calendarMonth->copy()->addMonth()->format('Y-m')])) }}">›</a>
            </div>
            <div class="calendar-picker__grid calendar-picker__weekdays" aria-hidden="true">
                @foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $weekday)<span>{{ $weekday }}</span>@endforeach
            </div>
            <div class="calendar-picker__grid" role="grid" aria-label="Choose a date or date range">
                @foreach ($calendarDays as $day)
                    @php $dateValue = $day->toDateString(); @endphp
                    <button type="button"
                            class="calendar-day {{ $day->month !== $calendarMonth->month ? 'calendar-day--outside' : '' }}"
                            data-calendar-date="{{ $dateValue }}"
                            aria-label="{{ $day->format('j F Y') }}">
                        <span>{{ $day->day }}</span>
                        @if ($activityDateLookup->has($dateValue))<i aria-label="Has checkpoint activity"></i>@endif
                    </button>
                @endforeach
            </div>
        </div>

        @foreach (request()->except(['from', 'to', 'date', 'page']) as $name => $value)
            @if (is_scalar($value))
                <input type="hidden" name="{{ $name }}" value="{{ $value }}">
            @endif
        @endforeach

        <div class="row-wrap">
            <button class="m3-btn m3-btn--filled m3-btn--sm" type="submit">Apply dates</button>
            <a class="m3-chip m3-chip--outlined" href="{{ route('admin.dashboard', array_merge($query, ['from' => today()->toDateString(), 'to' => today()->toDateString()])) }}">Today</a>
            <span class="calendar-activity-key"><span></span> Dates with checkpoint activity</span>
        </div>
    </form>

    <section class="m3-card m3-card--flat stack-sm dashboard-map-card">
        <div class="row-between">
            <div>
                <div class="m3-card__title">Latest employee locations</div>
                <p class="m3-help">One marker per employee, using their latest checkpoint in this selection.</p>
            </div>
        </div>
        <div id="admin-map" class="map map--dashboard"></div>
        <div class="map-legend">
            <span class="map-legend__item">{{ $points->count() }} latest employee locations</span>
        </div>
    </section>

    <section>
        <div class="section-heading">
            <div>
                <div class="md-label">Overview</div>
                <h2 class="m3-card__title">{{ $rangeLabel }}</h2>
            </div>
        </div>
        <div class="admin-grid admin-grid--overview">
            @foreach ([
                ['Active employees', $stats['employees'], 'people'],
                ['Check ins', $stats['check_ins'], 'login'],
                ['Check outs', $stats['check_outs'], 'logout'],
                ['Checkpoints', $stats['checkpoints'], 'add_location_alt'],
                ['Avg. working hours', $stats['average_hours'], 'schedule'],
            ] as [$label, $value, $icon])
                <div class="stat-card">
                    <x-md-icon name="{{ $icon }}" size="sm" />
                    <div class="stat-card__value">{{ is_numeric($value) ? number_format($value) : $value }}</div>
                    <div class="stat-card__label">{{ $label }}</div>
                </div>
            @endforeach
        </div>
    </section>

    <form class="m3-card m3-card--flat" method="GET" action="{{ route('admin.dashboard') }}">
        <div class="m3-card__header">
            <span class="m3-card__title">Filter criteria</span>
            <span class="md-muted">{{ $activeFilters }} active</span>
        </div>
        <input type="hidden" name="from" value="{{ $filters['from'] }}">
        <input type="hidden" name="to" value="{{ $filters['to'] }}">
        <div class="filter-grid">
            <div class="m3-field">
                <label class="m3-field__label" for="filter-employee">Employee</label>
                <select class="m3-select" id="filter-employee" name="employee">
                    <option value="">All employees</option>
                    @foreach ($employees as $person)
                        <option value="{{ $person->id }}" @selected((int) $filters['employee'] === $person->id)>{{ $person->name }} ({{ $person->employee_id }})</option>
                    @endforeach
                </select>
            </div>
            <div class="m3-field">
                <label class="m3-field__label" for="filter-code">Employee ID</label>
                <input class="m3-input" id="filter-code" name="employee_code" value="{{ $filters['employee_code'] }}" placeholder="EMP001">
            </div>
            <div class="m3-field">
                <label class="m3-field__label" for="filter-status">Attendance status</label>
                <select class="m3-select" id="filter-status" name="status">
                    <option value="">All statuses</option>
                    <option value="open" @selected($filters['status'] === 'open')>One checkpoint</option>
                    <option value="complete" @selected($filters['status'] === 'complete')>Two or more checkpoints</option>
                </select>
            </div>
        </div>
        <div class="row-wrap" style="margin-top:14px">
            <button class="m3-btn m3-btn--filled m3-btn--sm" type="submit"><x-md-icon name="tune" size="sm" />Apply filters</button>
            <a class="m3-btn m3-btn--text m3-btn--sm" href="{{ route('admin.dashboard') }}"><x-md-icon name="refresh" size="sm" />Reset</a>
        </div>
    </form>

    <section class="stack-md">
        <div class="row-between">
            <div>
                <div class="md-label">Employee daily attendance</div>
                <div class="m3-card__title">{{ $summaries->total() }} daily summaries</div>
            </div>
            <a class="m3-btn m3-btn--tonal m3-btn--sm" href="{{ route('admin.dashboard.export', request()->query()) }}"><x-md-icon name="download" size="sm" />Export CSV</a>
        </div>

        <div class="m3-card m3-card--flat">
            @if ($summaries->isEmpty())
                <div class="empty-state"><x-md-icon name="event_busy" size="lg" /><strong>No attendance matches this selection</strong><span>Choose another date or adjust the filters.</span></div>
            @else
                <div class="admin-table-wrap admin-table-wrap--always">
                    <table class="admin-table">
                        <thead><tr><th>Employee</th><th>Date</th><th>Check in</th><th>Check out</th><th>Working hours</th><th>Checkpoints</th><th>Actions</th></tr></thead>
                        <tbody>
                        @foreach ($summaries as $summary)
                            @php
                                $checkIn = $summary->check_in_at ? \Illuminate\Support\Carbon::parse($summary->check_in_at) : null;
                                $checkOut = $summary->checkpoint_count >= 2 ? \Illuminate\Support\Carbon::parse($summary->latest_checkpoint_at) : null;
                                $working = $checkIn && $checkOut ? sprintf('%02d:%02d', intdiv($checkIn->diffInMinutes($checkOut), 60), $checkIn->diffInMinutes($checkOut) % 60) : '—';
                            @endphp
                            <tr data-employee-code="{{ $summary->employee_id }}">
                                <td><strong>{{ $summary->employee_name }}</strong><div class="md-muted">{{ $summary->employee_id }}</div></td>
                                <td>{{ \Illuminate\Support\Carbon::parse($summary->attendance_date)->format('d M Y') }}</td>
                                <td>{{ $checkIn?->format('h:i A') ?? '—' }}</td>
                                <td>{{ $checkOut?->format('h:i A') ?? '—' }}</td>
                                <td>{{ $working }}</td>
                                <td><span class="m3-chip m3-chip--info">{{ $summary->checkpoint_count }}</span></td>
                                <td><a class="m3-btn m3-btn--text m3-btn--sm" href="{{ route('admin.trail', ['employee' => $summary->user_id, 'date' => $summary->attendance_date]) }}">View activity</a></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
        {{ $summaries->links() }}
    </section>

    <script type="application/json" id="admin-map-data">@json($points)</script>
@endsection
