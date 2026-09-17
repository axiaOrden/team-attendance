<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#fef7ff">
    <title>{{ config('app.name', 'Attendance') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<div class="m3-app" style="justify-content:center;padding:24px 16px 40px">
    <div style="width:100%;max-width:440px;margin:0 auto;display:flex;flex-direction:column;gap:20px">
        <div style="display:flex;align-items:center;justify-content:center;gap:12px">
            <span class="admin-brand__mark"><x-md-icon name="my_location" /></span>
            <span class="stack-sm">
                <strong style="font-size:1.125rem">{{ config('app.name', 'Attendance') }}</strong>
                <span class="md-muted" style="font-size:.75rem">Employee attendance</span>
            </span>
        </div>

        <div class="m3-card">
            {{ $slot }}
        </div>
    </div>
</div>
</body>
</html>
