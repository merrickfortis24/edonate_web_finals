@props([
    'title' => null,
    'subtitle' => null,
    'padding' => 'p-5',
])

<section {{ $attributes->merge(['class' => 'rounded-xl bg-white shadow-md ring-1 ring-slate-200/80']) }}>
    @if ($title || $subtitle)
        <header class="border-b border-slate-100 px-5 py-4">
            @if ($title)
                <h2 class="text-lg font-bold text-slate-900">{{ $title }}</h2>
            @endif
            @if ($subtitle)
                <p class="mt-1 text-sm text-slate-500">{{ $subtitle }}</p>
            @endif
        </header>
    @endif
    <div class="{{ $padding }}">
        {{ $slot }}
    </div>
</section>