@props([
    'title',
    'subtitle' => null,
    'headerClass' => 'header',
    'leftClass' => 'header__left-group',
    'rightClass' => 'header__right',
])

<header class="{{ $headerClass }} d-flex align-items-center justify-content-between gap-3">
    <div class="{{ $leftClass }} d-flex align-items-center gap-3 flex-grow-1">
        {{ $slot }}
        <div class="flex-grow-1">
            <h1 class="header__title mb-0">{{ $title }}</h1>
            @if (!empty($subtitle))
                <p class="header__subtitle mb-0">{{ $subtitle }}</p>
            @endif
        </div>
    </div>

    @isset($actions)
        <div class="{{ $rightClass }} flex-shrink-0">
            {{ $actions }}
        </div>
    @endisset
</header>
