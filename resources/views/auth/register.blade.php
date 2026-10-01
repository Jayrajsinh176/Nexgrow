@extends('layouts.guest', ['title' => 'Create account · Nexgrow'])

@section('content')
<div class="mb-7">
    <span class="inline-flex items-center gap-2 rounded-full border border-nx-gold/50 bg-nx-gold/10 px-3 py-1 text-xs font-medium text-nx-gold">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l2.9 6.26L22 9.27l-5.2 4.73L18.18 21 12 17.27 5.82 21l1.38-7L2 9.27l7.1-1.01L12 2z" /></svg>
        Join Nexgrow
    </span>
    <h1 class="mt-4 text-2xl font-bold text-white sm:text-3xl">Create your <span class="nx-gold-text">account</span></h1>
    <p class="mt-1.5 text-sm text-slate-400">Join Nexgrow and start building your team.</p>
</div>

<form method="POST" action="{{ route('register') }}" class="space-y-5">
    @csrf

    <div>
        <label for="referral_code" class="nx-label">Referral code <span class="text-rose-400">*</span></label>
        <div class="nx-field">
            <svg xmlns="http://www.w3.org/2000/svg" class="nx-field-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" /></svg>
            <input id="referral_code" name="referral_code" type="text" value="{{ old('referral_code', $referralCode) }}" required autocomplete="off"
                placeholder="Sponsor's User ID"
                class="nx-input uppercase placeholder:normal-case">
        </div>
        @error('referral_code')
            <p id="referral-feedback" class="mt-1.5 text-sm text-rose-400">{{ $message }}</p>
        @else
            <p id="referral-feedback" class="mt-1.5 text-sm {{ $sponsorName ? 'text-emerald-400' : 'text-slate-500' }}">
                @if ($sponsorName) Sponsor: <strong>{{ $sponsorName }}</strong> @endif
            </p>
        @enderror
    </div>

    <div>
        <label for="name" class="nx-label">Full name</label>
        <div class="nx-field">
            <svg xmlns="http://www.w3.org/2000/svg" class="nx-field-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
            <input id="name" name="name" type="text" value="{{ old('name') }}" required autofocus autocomplete="name"
                placeholder="Your full name"
                @error('name') aria-invalid="true" @enderror
                class="nx-input">
        </div>
        @error('name') <p class="mt-1.5 text-sm text-rose-400">{{ $message }}</p> @enderror
    </div>

    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
        <div class="min-w-0">
            <label for="email" class="nx-label">Email</label>
            <div class="nx-field">
                <svg xmlns="http://www.w3.org/2000/svg" class="nx-field-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="off"
                    placeholder="you@example.com"
                    class="nx-input">
            </div>
            @error('email')
                <p id="email-feedback" class="mt-1.5 text-sm text-rose-400">{{ $message }}</p>
            @else
                <p id="email-feedback" class="mt-1.5 text-sm text-slate-500"></p>
            @enderror
        </div>

        <div class="min-w-0">
            <label for="mobile" class="nx-label">Mobile number</label>
            <div class="nx-field">
                <svg xmlns="http://www.w3.org/2000/svg" class="nx-field-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" /></svg>
                <input id="mobile" name="mobile" type="tel" value="{{ old('mobile') }}" required autocomplete="off"
                    inputmode="numeric" maxlength="10" pattern="[6-9][0-9]{9}"
                    placeholder="10-digit number"
                    class="nx-input">
            </div>
            @error('mobile')
                <p id="mobile-feedback" class="mt-1.5 text-sm text-rose-400">{{ $message }}</p>
            @else
                <p id="mobile-feedback" class="mt-1.5 text-sm text-slate-500">10-digit number starting with 6-9</p>
            @enderror
        </div>
    </div>

    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
        <div class="min-w-0">
            <label for="password" class="nx-label">Password</label>
            <div class="nx-field">
                <svg xmlns="http://www.w3.org/2000/svg" class="nx-field-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                <input id="password" name="password" type="password" required autocomplete="new-password"
                    placeholder="Create a password"
                    @error('password') aria-invalid="true" @enderror
                    class="nx-input pr-12">
                @include('auth.partials.password-toggle', ['target' => 'password'])
            </div>
            @error('password') <p class="mt-1.5 text-sm text-rose-400">{{ $message }}</p> @enderror
        </div>

        <div class="min-w-0">
            <label for="password_confirmation" class="nx-label">Confirm password</label>
            <div class="nx-field">
                <svg xmlns="http://www.w3.org/2000/svg" class="nx-field-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" /></svg>
                <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password"
                    placeholder="Repeat password"
                    class="nx-input pr-12">
                @include('auth.partials.password-toggle', ['target' => 'password_confirmation'])
            </div>
        </div>
    </div>

    <button type="submit" id="submit-btn" disabled class="nx-btn-gold">
        Create account
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6" /></svg>
    </button>
</form>

<p class="mt-6 text-center text-sm text-slate-400">
    Already have an account?
    <a href="{{ route('login') }}" class="font-semibold text-nx-gold hover:underline">Log in</a>
</p>

<script>
    (function () {
        const submitBtn = document.getElementById('submit-btn');
        const referralUrl = '{{ route('register.check-referral') }}';
        const availabilityUrl = '{{ route('register.check-availability') }}';
        const fieldState = { referral_code: false, email: false, mobile: false };

        function refreshSubmit() {
            submitBtn.disabled = !(fieldState.referral_code && fieldState.email && fieldState.mobile);
        }

        function paint(feedback, ok, text) {
            feedback.textContent = text ?? '';
            feedback.className = 'mt-1.5 text-sm ' + (
                ok === true ? 'text-emerald-400' :
                ok === false ? 'text-rose-400' :
                'text-slate-500'
            );
        }

        // --- Referral code ---
        (function () {
            const input = document.getElementById('referral_code');
            const feedback = document.getElementById('referral-feedback');
            let timer = null;

            function check(code) {
                fetch(referralUrl + '?code=' + encodeURIComponent(code))
                    .then((res) => res.json())
                    .then((data) => {
                        if (input.value.trim().toUpperCase() !== code) return; // stale response
                        fieldState.referral_code = data.valid;
                        paint(feedback, data.valid, data.valid ? 'Sponsor: ' + data.name : 'Referral code not found');
                        refreshSubmit();
                    })
                    .catch(() => {});
            }

            input.addEventListener('input', function () {
                clearTimeout(timer);
                const code = input.value.trim().toUpperCase();
                fieldState.referral_code = false;

                if (!code) {
                    paint(feedback, null, '');
                    refreshSubmit();
                    return;
                }

                timer = setTimeout(() => check(code), 400);
            });

            if (input.value.trim()) check(input.value.trim().toUpperCase());
        })();

        // --- Email / Mobile availability ---
        function wireAvailability(fieldName, formatValidator, formatMessage) {
            const input = document.getElementById(fieldName);
            const feedback = document.getElementById(fieldName + '-feedback');
            let timer = null;

            function check(value) {
                fetch(availabilityUrl + '?field=' + fieldName + '&value=' + encodeURIComponent(value))
                    .then((res) => res.json())
                    .then((data) => {
                        if (input.value.trim() !== value) return; // stale response
                        fieldState[fieldName] = data.available === true;
                        paint(feedback, data.available, data.available ? 'Available' : data.message);
                        refreshSubmit();
                    })
                    .catch(() => {});
            }

            input.addEventListener('input', function () {
                if (fieldName === 'mobile') {
                    input.value = input.value.replace(/\D/g, '').slice(0, 10);
                }

                clearTimeout(timer);
                const value = input.value.trim();
                fieldState[fieldName] = false;

                if (!value) {
                    paint(feedback, null, fieldName === 'mobile' ? '10-digit number starting with 6-9' : '');
                    refreshSubmit();
                    return;
                }

                if (!formatValidator(value)) {
                    paint(feedback, false, formatMessage);
                    refreshSubmit();
                    return;
                }

                timer = setTimeout(() => check(value), 400);
            });

            if (input.value.trim()) check(input.value.trim());
        }

        wireAvailability('email', (v) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v), 'Enter a valid email address');
        wireAvailability('mobile', (v) => /^[6-9]\d{9}$/.test(v), 'Enter a valid 10-digit mobile number');

        refreshSubmit();
    })();
</script>
@endsection
