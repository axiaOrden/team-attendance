@php
    $navUser = auth()->user();

    $navItems = $navUser?->isAdmin()
        ? [
            ['label' => 'Dashboard', 'icon' => 'dashboard', 'route' => 'admin.dashboard'],
            ['label' => 'Trail map', 'icon' => 'route', 'route' => 'admin.trail'],
            ['label' => 'Employees', 'icon' => 'people', 'route' => 'admin.employees'],
        ]
        : [
            ['label' => 'Attendance', 'icon' => 'check_circle', 'route' => 'dashboard'],
            ['label' => 'History', 'icon' => 'history', 'route' => 'attendance.history'],
            ['label' => 'Account', 'icon' => 'person', 'route' => 'profile.edit'],
        ];
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#fef7ff">
    <title>{{ $title ?: config('app.name', 'Attendance') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<div class="m3-app">
    <header class="m3-appbar">
        <div class="m3-avatar">{{ mb_strtoupper(mb_substr($navUser->name ?? 'A', 0, 1)) }}</div>
        <div class="m3-appbar__titles">
            <span class="m3-appbar__title">{{ $navUser->name }}</span>
            <span class="m3-appbar__subtitle">{{ $navUser->employee_id }} · {{ $navUser->isAdmin() ? 'Administrator' : 'Employee' }}</span>
        </div>
        <div style="flex:1"></div>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="m3-btn m3-btn--text m3-btn--sm" title="Log out">
                <x-md-icon name="logout" />
                <span class="sr-only">Log out</span>
            </button>
        </form>
    </header>

    <main class="m3-main">
        {{ $slot }}
    </main>

    <nav class="m3-bottomnav" aria-label="Main navigation">
        @foreach ($navItems as $item)
            <a class="m3-bottomnav__item"
               href="{{ route($item['route']) }}"
               @if (request()->routeIs($item['route'])) aria-current="page" @endif>
                <span class="md-icon-wrap"><x-md-icon name="{{ $item['icon'] }}" /></span>
                <span>{{ $item['label'] }}</span>
            </a>
        @endforeach
    </nav>
</div>
</body>
</html>
