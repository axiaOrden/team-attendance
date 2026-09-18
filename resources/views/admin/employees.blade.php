@extends('layouts.admin')

@section('title', 'Employees')

@section('content')
    <form class="m3-card m3-card--flat" method="GET" action="{{ route('admin.employees') }}">
        <div class="filter-grid">
            <div class="m3-field">
                <label class="m3-field__label" for="employee-search">Search</label>
                <input class="m3-input" type="search" id="employee-search" name="search"
                       value="{{ $search }}" placeholder="Name, employee ID or email" autocomplete="off">
            </div>
        </div>
        <div class="row-wrap" style="margin-top:12px">
            <button type="submit" class="m3-btn m3-btn--filled m3-btn--sm">
                <x-md-icon name="search" size="sm" />
                <span>Search</span>
            </button>
            @if ($search !== '')
                <a class="m3-btn m3-btn--text m3-btn--sm" href="{{ route('admin.employees') }}">
                    <x-md-icon name="refresh" size="sm" />
                    <span>Reset</span>
                </a>
            @endif
        </div>
    </form>

    <div class="m3-card m3-card--flat">
        @if ($employees->isEmpty())
            <div style="text-align:center;padding:12px">
                <x-md-icon name="people" size="lg" />
                <div class="m3-row__title" style="margin-top:8px">No employees found</div>
                <p class="m3-help">Employees appear here as soon as they register.</p>
            </div>
        @else
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Employee ID</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Records</th>
                            <th>Last activity</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($employees as $employee)
                            <tr>
                                <td>
                                    {{ $employee->name }}
                                    <div class="md-muted">{{ $employee->email }}</div>
                                </td>
                                <td>{{ $employee->employee_id }}</td>
                                <td>
                                    <form method="POST"
                                          action="{{ route('admin.employees.role', $employee) }}"
                                          data-confirm="Change the role of {{ $employee->name }}?">
                                        @csrf
                                        @method('PATCH')
                                        <select class="m3-select" name="role" style="min-height:44px" data-auto-submit>
                                            <option value="employee" @selected($employee->role === 'employee')>Employee</option>
                                            <option value="admin" @selected($employee->role === 'admin')>Administrator</option>
                                        </select>
                                    </form>
                                </td>
                                <td>
                                    <div class="stack-sm">
                                        <span class="m3-chip m3-chip--{{ $employee->isActive() ? 'success' : 'warning' }}">
                                            {{ $employee->isActive() ? 'Active' : 'Inactive' }}
                                        </span>
                                        @if ($employee->isEmployee())
                                            <form method="POST"
                                                  action="{{ route('admin.employees.status', $employee) }}"
                                                  data-confirm="{{ $employee->isActive() ? 'Deactivate' : 'Reactivate' }} {{ $employee->name }}?">
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="is_inactive" value="{{ $employee->isActive() ? 1 : 0 }}">
                                                <button type="submit" class="m3-btn m3-btn--{{ $employee->isActive() ? 'text' : 'tonal' }} m3-btn--sm">
                                                    {{ $employee->isActive() ? 'Deactivate' : 'Reactivate' }}
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                                <td>{{ number_format($employee->attendances_count) }}</td>
                                <td>
                                    @if ($employee->attendances_max_recorded_at)
                                        {{ \Illuminate\Support\Carbon::parse($employee->attendances_max_recorded_at)->isoFormat('D MMM YYYY, HH:mm') }}
                                    @else
                                        <span class="md-muted">Never</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="admin-cards">
                @foreach ($employees as $employee)
                    <article class="employee-card">
                        <header class="employee-card__header">
                            <div class="m3-row__icon">
                                <x-md-icon name="{{ $employee->role === 'admin' ? 'account_circle' : 'person' }}" size="sm" />
                            </div>
                            <div class="employee-card__identity">
                                <div class="m3-row__title">{{ $employee->name }}</div>
                                <div class="m3-row__meta">{{ $employee->employee_id }}</div>
                            </div>
                            <span class="m3-chip m3-chip--{{ $employee->isActive() ? 'success' : 'warning' }}">
                                {{ $employee->isActive() ? 'Active' : 'Inactive' }}
                            </span>
                        </header>

                        <dl class="employee-card__details">
                            <div>
                                <dt>Email</dt>
                                <dd>{{ $employee->email }}</dd>
                            </div>
                            <div>
                                <dt>Attendance records</dt>
                                <dd>{{ number_format($employee->attendances_count) }}</dd>
                            </div>
                            <div>
                                <dt>Last activity</dt>
                                <dd>{{ $employee->attendances_max_recorded_at
                                    ? \Illuminate\Support\Carbon::parse($employee->attendances_max_recorded_at)->isoFormat('D MMM YYYY, HH:mm')
                                    : 'Never' }}</dd>
                            </div>
                        </dl>

                        <div class="employee-card__actions">
                            <form method="POST"
                                  action="{{ route('admin.employees.role', $employee) }}"
                                  data-confirm="Change the role of {{ $employee->name }}?">
                                @csrf
                                @method('PATCH')
                                <label class="m3-field__label" for="role-{{ $employee->id }}">Role</label>
                                <select id="role-{{ $employee->id }}" class="m3-select" name="role" data-auto-submit>
                                    <option value="employee" @selected($employee->role === 'employee')>Employee</option>
                                    <option value="admin" @selected($employee->role === 'admin')>Administrator</option>
                                </select>
                            </form>

                            @if ($employee->isEmployee())
                                <form method="POST"
                                      action="{{ route('admin.employees.status', $employee) }}"
                                      data-confirm="{{ $employee->isActive() ? 'Deactivate' : 'Reactivate' }} {{ $employee->name }}?">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="is_inactive" value="{{ $employee->isActive() ? 1 : 0 }}">
                                    <button type="submit" class="m3-btn m3-btn--{{ $employee->isActive() ? 'outlined' : 'tonal' }} m3-btn--sm">
                                        {{ $employee->isActive() ? 'Deactivate employee' : 'Reactivate employee' }}
                                    </button>
                                </form>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </div>

    {{ $employees->links() }}
@endsection
