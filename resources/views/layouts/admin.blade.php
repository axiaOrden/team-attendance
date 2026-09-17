@php
    $admin = auth()->user();
    $links = [
        ['label' => 'Dashboard', 'icon' => 'dashboard', 'route' => 'admin.dashboard'],
        ['label' => 'Trail map', 'icon' => 'route', 'route' => 'admin.trail'],
        ['label' => 'Employees', 'icon' => 'people', 'route' => 'admin.employees'],
    ];
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#fef7ff">
    <title>@yield('title', 'Administration') · {{ config('app.name', 'Attendance') }}</title>

    <link rel="stylesheet" href="{{ asset('vendor/leaflet/leaflet.css') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="admin-body">
<div class="admin-shell">
    <aside class="admin-sidebar">
        <div class="admin-sidebar__brand">
            <span class="admin-brand__mark"><x-md-icon name="my_location" /></span>
            <span class="stack-sm">
                <strong>{{ config('app.name', 'Attendance') }}</strong>
                <span class="md-muted" style="font-size:.75rem">Administration</span>
            </span>
        </div>

        @foreach ($links as $link)
            <a class="admin-sidebar__link"
               href="{{ route($link['route']) }}"
               @if (request()->routeIs($link['route'])) aria-current="page" @endif>
                <x-md-icon name="{{ $link['icon'] }}" />
                <span>{{ $link['label'] }}</span>
            </a>
        @endforeach

        <div style="flex:1"></div>

        <div class="m3-card m3-card--flat" style="padding:14px">
            <div class="md-label">Signed in</div>
            <div class="m3-row__title">{{ $admin->name }}</div>
            <div class="md-muted" style="font-size:.75rem">{{ $admin->employee_id }}</div>
            <form method="POST" action="{{ route('logout') }}" style="margin-top:10px">
                @csrf
                <button type="submit" class="m3-btn m3-btn--outlined m3-btn--sm m3-btn--block">
                    <x-md-icon name="logout" size="sm" />
                    <span>Log out</span>
                </button>
            </form>
        </div>
    </aside>

    <div style="flex:1;min-width:0;display:flex;flex-direction:column">
        <div class="admin-topbar">
            <span class="admin-brand__mark" style="width:36px;height:36px">
                <x-md-icon name="my_location" size="sm" />
            </span>
            <div class="m3-appbar__titles">
                <span class="m3-appbar__title">@yield('title', 'Administration')</span>
                <span class="m3-appbar__subtitle">{{ $admin->name }}</span>
            </div>
            <div style="flex:1"></div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="m3-btn m3-btn--text m3-btn--sm" title="Log out">
                    <x-md-icon name="logout" />
                </button>
            </form>
        </div>

        <nav class="admin-nav" aria-label="Administration navigation">
            @foreach ($links as $link)
                <a class="admin-nav__link"
                   href="{{ route($link['route']) }}"
                   @if (request()->routeIs($link['route'])) aria-current="page" @endif>
                    <x-md-icon name="{{ $link['icon'] }}" size="sm" />
                    <span>{{ $link['label'] }}</span>
                </a>
            @endforeach
        </nav>

        <main class="admin-main">
            @if (session('status'))
                <div class="m3-chip m3-chip--success" style="height:auto;padding:10px 14px">
                    <x-md-icon name="check_circle" size="sm" />
                    <span>{{ session('status') }}</span>
                </div>
            @endif

            @if ($errors->any() && ! $errors->hasBag('default'))
                <div class="m3-chip m3-chip--error" style="height:auto;padding:10px 14px">
                    <x-md-icon name="error" size="sm" />
                    <span>{{ $errors->first() }}</span>
                </div>
            @endif

            @yield('content')
        </main>
    </div>
</div>

<script src="{{ asset('vendor/leaflet/leaflet.js') }}"></script>

<div class="m3-dialog" data-photo-dialog>
    <div class="m3-dialog__panel">
        <img class="m3-dialog__image" data-photo-dialog-image alt="Watermarked attendance photo">
        <div class="row-between" style="margin-top:14px">
            <a class="m3-btn m3-btn--text m3-btn--sm" data-photo-dialog-original href="#" target="_blank" rel="noopener">
                <x-md-icon name="image" size="sm" />
                <span>Original photo</span>
            </a>
            <button type="button" class="m3-btn m3-btn--filled m3-btn--sm" data-photo-dialog-close>
                <span>Close</span>
            </button>
        </div>
    </div>
</div>

@stack('scripts')
</body>
</html>
