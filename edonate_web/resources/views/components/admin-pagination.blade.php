@props(['paginator'])

@php
    $total = (int) $paginator->total();
    $firstItem = $total > 0 ? (int) ($paginator->firstItem() ?? 0) : 0;
    $lastItem = $total > 0 ? (int) ($paginator->lastItem() ?? 0) : 0;
@endphp

<div {{ $attributes->merge(['class' => 'admin-pagination']) }} aria-label="Table pagination">
    <p class="admin-pagination__info mb-0">
        Showing {{ $firstItem }} to {{ $lastItem }} of {{ $total }} entries
    </p>

    @if ($paginator->hasPages())
        <nav class="admin-pagination__links" aria-label="Pagination links">
            {{ $paginator->onEachSide(1)->links('pagination::bootstrap-5') }}
        </nav>
    @else
        <span class="admin-pagination__links" aria-hidden="true"></span>
    @endif
</div>
