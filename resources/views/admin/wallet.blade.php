@extends('layouts.dashboard', ['title' => 'Manage Wallets'])

@section('content')
<div class="mx-auto max-w-xl space-y-6">
    @if (session('status'))
        <div class="rounded-md bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
            {{ session('status') }}
        </div>
    @endif

    <div class="rounded-lg border border-slate-200 bg-white p-6">
        <h2 class="mb-4 text-sm font-medium text-slate-700">Credit / Debit Tokens</h2>

        <form method="POST" id="wallet-form" class="space-y-4">
            @csrf

            <div>
                <label for="user_id" class="mb-1 block text-sm font-medium text-slate-700">User ID</label>
                <input id="user_id" name="user_id" type="text" value="{{ old('user_id') }}" required autocomplete="off"
                    class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm uppercase focus:border-slate-500 focus:outline-none">
                @error('user_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="amount" class="mb-1 block text-sm font-medium text-slate-700">Amount</label>
                <input id="amount" name="amount" type="number" step="0.01" min="0.01" value="{{ old('amount') }}" required
                    class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none">
                @error('amount') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="note" class="mb-1 block text-sm font-medium text-slate-700">Note <span class="font-normal text-slate-400">(optional)</span></label>
                <input id="note" name="note" type="text" value="{{ old('note') }}"
                    class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none">
                @error('note') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="flex gap-3">
                <button type="submit" formaction="{{ route('admin.wallet.credit') }}"
                    class="flex-1 rounded-md bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-500">
                    Credit
                </button>
                <button type="submit" formaction="{{ route('admin.wallet.debit') }}"
                    class="flex-1 rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-500">
                    Debit
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
