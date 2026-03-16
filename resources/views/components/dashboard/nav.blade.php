@props([
    'links' => [],
    'current' => '',
    'userName' => 'Donor',
])

<header class="sticky top-0 z-40 border-b border-red-100/80 bg-white/90 backdrop-blur lg:hidden">
    <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-3">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-red-700">eDonate</p>
            <p class="text-sm font-semibold text-slate-800">Welcome Back, {{ $userName }}</p>
        </div>
        <form method="POST" action="{{ route('donor.logout') }}">
            @csrf
            <button type="submit" class="rounded-lg border border-red-200 px-3 py-1.5 text-xs font-semibold text-red-700 transition hover:bg-red-50">
                Logout
            </button>
        </form>
    </div>
    <nav class="mx-auto flex max-w-7xl gap-2 overflow-x-auto px-4 pb-3" aria-label="Primary navigation">
        @foreach ($links as $link)
            @php
                $isActive = $current === ($link['key'] ?? '');
            @endphp
            <a
                href="{{ $link['href'] }}"
                class="whitespace-nowrap rounded-full px-3 py-1.5 text-xs font-semibold transition {{ $isActive ? 'bg-red-700 text-white' : 'bg-red-50 text-red-700 hover:bg-red-100' }}"
            >
                {{ $link['label'] }}
            </a>
        @endforeach
    </nav>
</header>

<aside class="fixed inset-y-0 left-0 z-30 hidden w-64 flex-col border-r border-red-100/80 bg-white lg:flex">
    <div class="border-b border-red-100 px-6 py-5">
        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-red-700">eDonate</p>
        <h1 class="mt-2 text-xl font-bold text-slate-900">Donor Dashboard</h1>
        <p class="mt-1 text-sm text-slate-500">Welcome back, {{ $userName }}</p>
    </div>

    <nav class="flex-1 space-y-2 px-4 py-6" aria-label="Sidebar navigation">
        @foreach ($links as $link)
            @php
                $isActive = $current === ($link['key'] ?? '');
            @endphp
            <a
                href="{{ $link['href'] }}"
                class="flex items-center rounded-xl px-3 py-2.5 text-sm font-semibold transition {{ $isActive ? 'bg-red-700 text-white shadow-md' : 'text-slate-700 hover:bg-red-50 hover:text-red-700' }}"
            >
                {{ $link['label'] }}
            </a>
        @endforeach
    </nav>

    <div class="border-t border-red-100 px-4 py-4">
        <form method="POST" action="{{ route('donor.logout') }}">
            @csrf
            <button type="submit" class="w-full rounded-xl border border-red-200 px-4 py-2.5 text-sm font-semibold text-red-700 transition hover:bg-red-50">
                Logout
            </button>
        </form>
    </div>
</aside>