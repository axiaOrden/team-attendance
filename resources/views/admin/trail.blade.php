@extends('layouts.admin')

@section('title', 'Employee trail map')

@section('content')
    <div class="trail-layout">
        <section class="m3-card m3-card--flat stack-md">
            <form method="GET" action="{{ route('admin.trail') }}" class="stack-md">
                <div class="m3-field">
                    <label class="m3-field__label" for="trail-employee">Employee</label>
                    <select class="m3-select" id="trail-employee" name="employee" data-auto-submit>
                        @forelse ($employees as $person)
                            <option value="{{ $person->id }}" @selected($employee && $employee->id === $person->id)>
                                {{ $person->name }} ({{ $person->employee_id }})
                            </option>
                        @empty
                            <option value="">No users registered yet</option>
                        @endforelse
                    </select>
                </div>

                <div class="m3-field">
                    <label class="m3-field__label" for="trail-date">Date</label>
                    <input class="m3-input"
                           type="date"
                           id="trail-date"
                           name="date"
                           value="{{ $date->toDateString() }}"
                           max="{{ today()->toDateString() }}"
                           data-auto-submit>
                </div>

                <div class="row-wrap">
                    <a class="m3-chip {{ $date->isToday() ? 'm3-chip--info' : 'm3-chip--outlined' }}"
                       href="{{ route('admin.trail', ['employee' => $employee?->id, 'date' => 'today']) }}">Today</a>
                    <a class="m3-chip {{ $date->isYesterday() ? 'm3-chip--info' : 'm3-chip--outlined' }}"
                       href="{{ route('admin.trail', ['employee' => $employee?->id, 'date' => 'yesterday']) }}">Yesterday</a>
                    <a class="m3-chip m3-chip--outlined"
                       href="{{ route('admin.trail', ['employee' => $employee?->id, 'date' => $date->copy()->subDay()->toDateString()]) }}">
                        <x-md-icon name="arrow_back" size="sm" />
                        <span>Previous day</span>
                    </a>
                    @unless ($date->isToday())
                        <a class="m3-chip m3-chip--outlined"
                           href="{{ route('admin.trail', ['employee' => $employee?->id, 'date' => $date->copy()->addDay()->toDateString()]) }}">
                            <x-md-icon name="arrow_back" size="sm" class="m3-flip" />
                            <span>Next day</span>
                        </a>
                    @endunless
                </div>
            </form>

            <div class="m3-divider"></div>

            @if ($employee)
                <div>
                    <div class="m3-row__title" style="font-size:1.125rem">{{ $employee->name }}</div>
                    <div class="row-wrap" style="margin-top:8px">
                        <span class="m3-chip m3-chip--outlined">
                            <x-md-icon name="account_circle" size="sm" />
                            <span>{{ $employee->employee_id }}</span>
                        </span>
                        <span class="m3-chip m3-chip--outlined">
                            <x-md-icon name="calendar_today" size="sm" />
                            <span>{{ $date->isoFormat('D MMM YYYY') }}</span>
                        </span>
                        <span class="m3-chip m3-chip--{{ $records->isEmpty() ? 'warning' : 'success' }}">
                            {{ $records->count() }} {{ \Illuminate\Support\Str::plural('event', $records->count()) }}
                        </span>
                    </div>
                </div>

                <div class="m3-divider"></div>

                @if ($records->isEmpty())
                    <div style="text-align:center;padding:10px">
                        <x-md-icon name="route" size="lg" />
                        <div class="m3-row__title" style="margin-top:8px">No attendance recorded on this day</div>
                        <p class="m3-help">Pick another date or employee to see a trail.</p>
                    </div>
                @else
                    <div class="timeline">
                        @foreach ($records as $index => $record)
                            <article class="timeline__item" data-point-index="{{ $index }}">
                                <div class="timeline__rail">
                                    <span class="timeline__dot {{ $record->isCheckIn() ? 'timeline__dot--in' : 'timeline__dot--out' }}"></span>
                                </div>
                                <div class="timeline__body">
                                    <div class="row-between">
                                        <span class="timeline__time">{{ $record->recorded_at->format('H:i') }}</span>
                                        <span class="m3-chip m3-chip--{{ $record->isCheckIn() ? 'success' : 'info' }}">
                                            {{ $record->isCheckIn() ? 'CHECK IN' : 'CHECK OUT' }}
                                        </span>
                                    </div>

                                    <div class="m3-row__meta" style="margin-top:6px">
                                        <x-md-icon name="location_on" size="sm" />
                                        <span>{{ $record->locationLabel() }}</span>
                                        <span class="md-muted">· ±{{ $record->accuracyLabel() }}</span>
                                    </div>

                                    @if ($record->address)
                                        <div class="m3-help" style="margin-top:4px">{{ $record->address }}</div>
                                    @endif

                                    <div class="m3-help" style="margin-top:4px">
                                        {{ $record->deviceLabel() }} · {{ $record->coordinateLabel() }}
                                    </div>

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
                            </article>
                        @endforeach
                    </div>
                @endif
            @else
                <p class="m3-help">Register an employee first to see their trail.</p>
            @endif
        </section>

        <section class="m3-card m3-card--flat stack-sm">
            <div id="trail-map" class="map map--tall"></div>

            <div class="map-legend">
                <span class="map-legend__item">
                    <span class="map-legend__dot" style="background: var(--md-success)"></span> Check in
                </span>
                <span class="map-legend__item">
                    <span class="map-legend__dot" style="background: var(--md-primary)"></span> Check out
                </span>
                <span class="map-legend__item">Numbered in chronological order</span>
            </div>

            <p class="m3-help">
                The dashed line connects the recorded attendance points in chronological order
                (oldest first). It shows the relationship between the recorded events - it is
                <strong>not</strong> a record of the exact route travelled between them.
            </p>
        </section>
    </div>

    <script type="application/json" id="trail-map-data">@json($points)</script>
@endsection
