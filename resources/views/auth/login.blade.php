@extends('layouts.guest', ['title' => 'Log in · Nexgrow'])

@section('content')
<div class="mb-7">
    <span class="inline-flex items-center gap-2 rounded-full border border-emerald-400/40 bg-emerald-400/10 px-3 py-1 text-xs font-medium text-emerald-200">
        <span class="h-2 w-2 rounded-full bg-emerald-400 shadow-[0_0_8px] shadow-emerald-400"></span>
        Member Login
    </span>
    <h1 class="mt-4 text-2xl font-bold text-white sm:text-3xl">Welcome <span class="nx-gold-text">back</span></h1>
    <p class="mt-1.5 text-sm text-slate-400">Log in to your Nexgrow account to continue.</p>
</div>

<form method="POST" action="{{ route('login') }}" class="space-y-5">
    @csrf

    <div>
        <label for="login" class="nx-label">User ID, Email or Mobile</label>
        <div class="nx-field">
            <svg xmlns="http://www.w3.org/2000/svg" class="nx-field-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
            <input id="login" name="login" type="text" value="{{ old('login') }}" required autofocus autocomplete="username"
                placeholder="e.g. NX123456 or you@example.com"
                @error('login') aria-invalid="true" @enderror
                class="nx-input">
        </div>
        @error('login') <p class="mt-1.5 text-sm text-rose-400">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="password" class="nx-label">Password</label>
        <div class="nx-field">
            <svg xmlns="http://www.w3.org/2000/svg" class="nx-field-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
            <input id="password" name="password" type="password" required autocomplete="current-password"
                placeholder="Enter your password"
                @error('password') aria-invalid="true" @enderror
                class="nx-input pr-12">
            @include('auth.partials.password-toggle', ['target' => 'password'])
        </div>
        @error('password') <p class="mt-1.5 text-sm text-rose-400">{{ $message }}</p> @enderror
    </div>

    <label class="flex cursor-pointer items-center gap-2.5 text-sm text-slate-300 select-none">
        <input type="checkbox" name="remember" value="1" @checked(old('remember'))
            class="h-4 w-4 rounded border-white/20 bg-black/40 accent-nx-gold">
        Keep me logged in
    </label>

    <button type="submit" class="nx-btn-gold">
        Log in
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6" /></svg>
    </button>
</form>

<div class="my-6 flex items-center gap-3 text-xs text-slate-500">
    <span class="h-px flex-1 bg-white/10"></span>
    New to Nexgrow?
    <span class="h-px flex-1 bg-white/10"></span>
</div>

<a href="{{ route('register') }}"
    class="flex w-full items-center justify-center gap-2 rounded-xl border border-emerald-400/40 bg-emerald-400/5 px-4 py-3 text-sm font-semibold text-emerald-200 transition hover:border-nx-gold/60 hover:text-white">
    Create an account
</a>
@endsection
