<x-guest-layout>
<div class="stack-md">
    <div>
        <div class="md-label">Welcome back</div>
        <h1 class="md-title">Sign in</h1>
        <p class="m3-help">Use the email and password you registered with.</p>
    </div>

    @if (session('status'))
        <div class="m3-chip m3-chip--success" style="height:auto;padding:10px 14px">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('login') }}" class="stack-md">
        @csrf

        <div class="m3-field">
            <label class="m3-field__label" for="email">Email</label>
            <input class="m3-input" id="email" type="email" name="email" value="{{ old('email') }}"
                   required autofocus autocomplete="username" placeholder="john@company.com">
            @error('email')
                <span class="m3-error">{{ $message }}</span>
            @enderror
        </div>

        <div class="m3-field">
            <label class="m3-field__label" for="password">Password</label>
            <input class="m3-input" id="password" type="password" name="password"
                   required autocomplete="current-password">
            @error('password')
                <span class="m3-error">{{ $message }}</span>
            @enderror
        </div>

        <label class="row-wrap" style="gap:8px;cursor:pointer">
            <input type="checkbox" name="remember" style="width:18px;height:18px">
            <span class="m3-help">Remember me</span>
        </label>

        <div class="row-between">
            @if (Route::has('password.request'))
                <a class="m3-btn m3-btn--text m3-btn--sm" href="{{ route('password.request') }}">Forgot password?</a>
            @endif
            <button type="submit" class="m3-btn m3-btn--filled">
                <x-md-icon name="check_circle" size="sm" />
                <span>Log in</span>
            </button>
        </div>

        <div class="m3-divider"></div>

        <a class="m3-btn m3-btn--tonal m3-btn--block" href="{{ route('register') }}">
            <x-md-icon name="person" size="sm" />
            <span>Register as a new employee</span>
        </a>
    </form>
</div>

</x-guest-layout>
