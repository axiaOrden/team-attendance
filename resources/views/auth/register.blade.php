<x-guest-layout>
<div class="stack-md">
    <div>
        <div class="md-label">New employee</div>
        <h1 class="md-title">Create your account</h1>
        <p class="m3-help">Your employee ID must be unique - your administrator uses it to identify your attendance.</p>
    </div>

    <form method="POST" action="{{ route('register') }}" class="stack-md">
        @csrf

        <div class="m3-field">
            <label class="m3-field__label" for="name">Full name</label>
            <input class="m3-input" id="name" type="text" name="name" value="{{ old('name') }}"
                   required autofocus autocomplete="name" placeholder="John Doe">
            @error('name')
                <span class="m3-error">{{ $message }}</span>
            @enderror
        </div>

        <div class="m3-field">
            <label class="m3-field__label" for="employee_id">Employee ID</label>
            <input class="m3-input" id="employee_id" type="text" name="employee_id" value="{{ old('employee_id') }}"
                   required autocomplete="off" placeholder="EMP001">
            @error('employee_id')
                <span class="m3-error">{{ $message }}</span>
            @enderror
        </div>

        <div class="m3-field">
            <label class="m3-field__label" for="email">Email</label>
            <input class="m3-input" id="email" type="email" name="email" value="{{ old('email') }}"
                   required autocomplete="username" placeholder="john@company.com">
            @error('email')
                <span class="m3-error">{{ $message }}</span>
            @enderror
        </div>

        <div class="m3-field">
            <label class="m3-field__label" for="password">Password</label>
            <input class="m3-input" id="password" type="password" name="password"
                   required autocomplete="new-password">
            @error('password')
                <span class="m3-error">{{ $message }}</span>
            @enderror
        </div>

        <div class="m3-field">
            <label class="m3-field__label" for="password_confirmation">Confirm password</label>
            <input class="m3-input" id="password_confirmation" type="password" name="password_confirmation"
                   required autocomplete="new-password">
        </div>

        <div class="row-between">
            <a class="m3-btn m3-btn--text m3-btn--sm" href="{{ route('login') }}">Already registered?</a>
            <button type="submit" class="m3-btn m3-btn--filled">
                <x-md-icon name="check_circle" size="sm" />
                <span>Register</span>
            </button>
        </div>
    </form>
</div>

</x-guest-layout>
