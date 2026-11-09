@extends('layouts.dashboard', ['title' => 'My Profile', 'dark' => true])

@section('content')
<div class="mx-auto max-w-4xl space-y-6">
    {{-- Header Banner --}}
    <div class="nx-panel p-6 sm:p-8">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-4">
                <span class="nx-avatar flex h-16 w-16 shrink-0 items-center justify-center rounded-2xl text-2xl font-bold text-nx-gold border border-nx-gold/40 bg-nx-gold/10">
                    {{ strtoupper(mb_substr($user->name ?: $user->user_id, 0, 1)) }}
                </span>
                <div>
                    <h2 class="text-2xl font-bold text-white">{{ $user->name ?: $user->user_id }}</h2>
                    <p class="font-mono text-sm text-nx-gold">{{ $user->user_id }}</p>
                </div>
            </div>
            <div>
                <span class="inline-flex items-center gap-2 rounded-full border border-emerald-400/40 bg-emerald-400/10 px-3.5 py-1.5 text-xs font-semibold text-emerald-300">
                    <span class="h-2 w-2 rounded-full bg-emerald-400 shadow-[0_0_8px] shadow-emerald-400"></span>
                    Wallet Connected
                </span>
            </div>
        </div>
    </div>

    @if (session('status'))
        <div class="rounded-xl border border-emerald-400/40 bg-emerald-400/10 p-4 text-sm text-emerald-300">
            {{ session('status') }}
        </div>
    @endif

    {{-- Account Details & Name Form --}}
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        {{-- Read-only On-Chain Web3 Identity --}}
        <div class="nx-panel p-6 space-y-5 lg:col-span-1">
            <h3 class="text-lg font-semibold text-white border-b border-white/10 pb-3">Web3 Account</h3>

            <div>
                <p class="text-xs uppercase tracking-wider text-slate-400">User ID</p>
                <p class="mt-1 font-mono text-base font-bold text-nx-gold">{{ $user->user_id }}</p>
            </div>

            <div>
                <p class="text-xs uppercase tracking-wider text-slate-400">Linked Wallet Address</p>
                <p class="mt-1 font-mono text-xs text-white break-all bg-black/30 p-2.5 rounded-lg border border-white/5">
                    {{ $user->wallet_address }}
                </p>
            </div>

            <div>
                <p class="text-xs uppercase tracking-wider text-slate-400">Sponsor (Upline)</p>
                @if ($user->sponsor)
                    <p class="mt-1 text-sm font-semibold text-white">
                        {{ $user->sponsor->name ?: $user->sponsor->user_id }}
                        @if ($user->sponsor->name)
                            <span class="font-mono text-xs text-slate-400">({{ $user->sponsor->user_id }})</span>
                        @endif
                    </p>
                @else
                    <p class="mt-1 text-sm text-slate-500">Root User</p>
                @endif
            </div>

            <div>
                <p class="text-xs uppercase tracking-wider text-slate-400">Joined Date</p>
                <p class="mt-1 text-sm text-slate-300">{{ $user->created_at->format('d M Y, h:i A') }}</p>
            </div>
        </div>

        {{-- Editable Profile Information --}}
        <div class="nx-panel p-6 lg:col-span-2">
            <h3 class="text-lg font-semibold text-white border-b border-white/10 pb-3">Profile Details</h3>
            <p class="mt-2 text-sm text-slate-400">
                Set or change your display name. This name is shown to your upline and downline team members.
            </p>

            <form method="POST" action="{{ route('profile.update') }}" class="mt-6 space-y-5">
                @csrf
                @method('PATCH')

                <div>
                    <label for="name" class="nx-label">Display Name</label>
                    <div class="nx-field">
                        <svg xmlns="http://www.w3.org/2000/svg" class="nx-field-icon text-nx-gold" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                        <input id="name" name="name" type="text" value="{{ old('name', $user->name) }}" required
                            placeholder="Enter your name"
                            class="nx-input">
                    </div>
                    @error('name')
                        <p class="mt-1.5 text-sm text-rose-400">{{ $message }}</p>
                    @enderror
                </div>

                <div class="pt-2">
                    <button type="submit" class="nx-btn-gold">
                        Save Profile
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

