@props([
    'title',
    'subtitle' => null,
    'headerClass' => 'header',
    'leftClass' => 'header__left-group',
    'rightClass' => 'header__right',
])

<header class="{{ $headerClass }}">
    <div class="{{ $leftClass }}">
        {{ $slot }}
        <div>
            <h1 class="header__title">{{ $title }}</h1>
            @if (!empty($subtitle))
                <p class="header__subtitle">{{ $subtitle }}</p>
            @endif
        </div>
    </div>

    @isset($actions)
        <div class="{{ $rightClass }}">
            {{ $actions }}
        </div>
    @endisset
</header>
