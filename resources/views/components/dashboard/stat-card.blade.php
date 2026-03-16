@props([
    'label',
    'value',
])

<div class="rounded-xl bg-white/95 p-4 shadow-md ring-1 ring-red-100 backdrop-blur">
    <p class="text-xs font-semibold uppercase tracking-[0.14em] text-red-700">{{ $label }}</p>
    <p class="mt-2 text-2xl font-bold text-slate-900">{{ $value }}</p>
</div>