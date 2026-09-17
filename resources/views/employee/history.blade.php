@php
    use Illuminate\Support\Carbon;
@endphp

<x-app-layout :title="'Attendance history'">
    <div class="stack-md">
        <section class="m3-card">
            <div class="md-label">Attendance history</div>
            <h1 class="md-headline" style="margin:2px 0 10px">{{ $user->name }}</h1>
            <div class="row-wrap">
                <span class="m3-chip m3-chip--outlined">
                    <x-md-icon name="account_circle" size="sm" />
                    <span>{{ $user->employee_id }}</span>
                </span>
                <span class="m3-chip m3-chip--outlined">
                    <x-md-icon name="history" size="sm" />
                    <span>{{ $records->total() }} records</span>
                </span>
            </div>
        </section>

        @forelse ($days as $date => $dayRecords)
            <section class="m3-card">
                <div class="m3-card__header">
                    <span class="m3-card__title">{{ Carbon::parse($date)->isoFormat('D MMMM YYYY') }}</span>
                    <span class="md-muted" style="font-size:.75rem">{{ Carbon::parse($date)->isoFormat('dddd') }}</span>
                </div>

                <div class="m3-list">
                    @foreach ($dayRecords as $record)
                        <div class="m3-row">
                            <div class="m3-row__icon {{ $record->isCheckIn() ? 'm3-row__icon--success' : '' }}">
                                {{ $record->isCheckIn() ? '↓' : '↑' }}
                            </div>
                            <div class="m3-row__body">
                                <div class="m3-row__title">
                                    {{ $record->isCheckIn() ? 'CHECK IN' : 'CHECK OUT' }}
                                    <span class="md-muted" style="font-weight:400">· {{ $record->recorded_at->format('h:i A') }}</span>
                                </div>
                                <div class="m3-row__meta">
                                    <x-md-icon name="location_on" size="sm" />
                                    <span>{{ $record->locationLabel() }}</span>
                                </div>
                                <div class="m3-row__meta">
                                    <span class="md-muted">Accurate to {{ $record->accuracyLabel() }}</span>
                                </div>

                                @if ($record->photoUrl())
                                    <div class="row-wrap" style="margin-top:8px">
                                        <a class="m3-chip m3-chip--outlined" href="{{ $record->photoUrl() }}" target="_blank" rel="noopener">
                                            <x-md-icon name="photo_camera" size="sm" />
                                            <span>View Photo</span>
                                        </a>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @empty
            <section class="m3-card" style="text-align:center">
                <x-md-icon name="history" size="lg" />
                <div class="m3-row__title" style="margin-top:10px">No attendance history yet</div>
                <p class="m3-help">Once you check in, your records and photos will be listed here.</p>
                <a class="m3-btn m3-btn--filled m3-btn--sm" style="margin-top:14px" href="{{ route('dashboard') }}">
                    <span>Go to attendance</span>
                </a>
            </section>
        @endforelse

        <div>
            {{ $records->links() }}
        </div>
    </div>
</x-app-layout>
