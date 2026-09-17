<x-app-layout :title="'Account'">
<div class="stack-md">
    <div>
        <div class="md-label">Account</div>
        <h1 class="md-title">Edit profile</h1>
        <p class="m3-help">Update your details, password, or delete your account.</p>
    </div>

    <div class="m3-card m3-card--flat">
        <div class="m3-card__header">
            <span class="m3-card__title">Profile information</span>
            <x-md-icon name="account_circle" size="sm" />
        </div>

        <form method="POST" action="{{ route('profile.update') }}" class="stack-md">
            @csrf
            @method('patch')

            <div class="m3-field">
                <label class="m3-field__label" for="name">Full name</label>
                <input class="m3-input" id="name" name="name" type="text" value="{{ old('name', $user->name) }}" required autocomplete="name">
                @error('name')<span class="m3-error">{{ $message }}</span>@enderror
            </div>

            <div class="m3-field">
                <label class="m3-field__label" for="readonly-employee-id">Employee ID</label>
                <input class="m3-input" id="readonly-employee-id" type="text" value="{{ $user->employee_id }}" disabled>
                <span class="m3-help">Your employee ID is fixed and appears on every attendance record.</span>
            </div>

            <div class="m3-field">
                <label class="m3-field__label" for="email">Email</label>
                <input class="m3-input" id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required autocomplete="username">
                @error('email')<span class="m3-error">{{ $message }}</span>@enderror

                @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                    <span class="m3-help">
                        Your email address is unverified.
                        <button form="send-verification" class="m3-btn m3-btn--text m3-btn--sm" type="submit">Resend verification email</button>
                    </span>
                @endif
            </div>

            <div class="row-wrap">
                <button type="submit" class="m3-btn m3-btn--filled m3-btn--sm">
                    <x-md-icon name="check_circle" size="sm" />
                    <span>Save</span>
                </button>
                @if (session('status') === 'profile-updated')
                    <span class="m3-chip m3-chip--success">Saved</span>
                @endif
            </div>
        </form>

        @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
            <form id="send-verification" method="POST" action="{{ route('verification.send') }}">
                @csrf
            </form>
        @endif
    </div>

    <div class="m3-card m3-card--flat">
        <div class="m3-card__header">
            <span class="m3-card__title">Update password</span>
            <x-md-icon name="lock" size="sm" />
        </div>

        <form method="POST" action="{{ route('password.update') }}" class="stack-md">
            @csrf
            @method('put')

            <div class="m3-field">
                <label class="m3-field__label" for="current_password">Current password</label>
                <input class="m3-input" id="current_password" name="current_password" type="password" autocomplete="current-password">
                @error('current_password', 'updatePassword')<span class="m3-error">{{ $message }}</span>@enderror
            </div>

            <div class="m3-field">
                <label class="m3-field__label" for="new_password">New password</label>
                <input class="m3-input" id="new_password" name="password" type="password" autocomplete="new-password">
                @error('password', 'updatePassword')<span class="m3-error">{{ $message }}</span>@enderror
            </div>

            <div class="m3-field">
                <label class="m3-field__label" for="password_confirmation">Confirm password</label>
                <input class="m3-input" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password">
            </div>

            <div class="row-wrap">
                <button type="submit" class="m3-btn m3-btn--filled m3-btn--sm">
                    <x-md-icon name="lock" size="sm" />
                    <span>Update password</span>
                </button>
                @if (session('status') === 'password-updated')
                    <span class="m3-chip m3-chip--success">Updated</span>
                @endif
            </div>
        </form>
    </div>

    <div class="m3-card m3-card--flat" style="border-color: var(--md-error)">
        <div class="m3-card__header">
            <span class="m3-card__title">Delete account</span>
            <x-md-icon name="error" size="sm" />
        </div>
        <p class="m3-help">
            Deleting your account also removes your attendance records and photos. This cannot be undone.
        </p>

        <form method="POST" action="{{ route('profile.destroy') }}" style="margin-top:12px"
              data-confirm="Delete your account and every attendance record? This cannot be undone.">
            @csrf
            @method('delete')

            <div class="m3-field">
                <label class="m3-field__label" for="delete_password">Confirm with your password</label>
                <input class="m3-input" id="delete_password" name="password" type="password" autocomplete="current-password" placeholder="Password">
                @error('password', 'userDeletion')<span class="m3-error">{{ $message }}</span>@enderror
            </div>

            <button type="submit" class="m3-btn m3-btn--outlined m3-btn--error" style="margin-top:12px">
                <x-md-icon name="error" size="sm" />
                <span>Delete account</span>
            </button>
        </form>
    </div>
</div>

</x-app-layout>
