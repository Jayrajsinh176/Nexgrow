{{-- This month's and last month's pool for Monthly Loyalty or Royalty, read
     from the contract by the "admin-monthly" widget (resources/js/nexgrow/admin.js).
     Expects $contract (contract key) and $count (its registered-count function). --}}
<section class="nx-panel overflow-hidden" data-nx-chain="admin-monthly">
    <div class="border-b border-white/10 px-6 py-4">
        <h3 class="font-bold text-white">Monthly Pool (on-chain)</h3>
        <p class="text-xs text-slate-400">Each pool is 5% of that month's business. Members register in the 7 days after the month; the keeper's monthly task then settles it.</p>
    </div>
    <dl class="grid grid-cols-2 gap-3 p-6 text-xs lg:grid-cols-6" data-nx-contract="{{ $contract }}" data-nx-count="{{ $count }}">
        <div><dt class="text-slate-400">This month</dt><dd class="mt-1 font-semibold text-white" data-nx-item="month">—</dd></div>
        <div><dt class="text-slate-400">Pool so far (NXD)</dt><dd class="mt-1 font-mono font-semibold text-emerald-300" data-nx-item="pool">—</dd></div>
        <div><dt class="text-slate-400">Last month</dt><dd class="mt-1 font-semibold text-white" data-nx-item="last-month">—</dd></div>
        <div><dt class="text-slate-400">Pool (NXD)</dt><dd class="mt-1 font-mono font-semibold text-white" data-nx-item="last-pool">—</dd></div>
        <div><dt class="text-slate-400">Registered</dt><dd class="mt-1 font-mono font-semibold text-white" data-nx-item="registered">—</dd></div>
        <div><dt class="text-slate-400">Registration</dt><dd class="mt-1 font-semibold text-nx-gold" data-nx-item="registration">—</dd></div>
    </dl>
</section>
