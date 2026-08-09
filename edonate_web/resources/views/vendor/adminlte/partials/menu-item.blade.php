@php
    use ColorlibHQ\AdminLte\Menu\MenuItemHelper;

    $isHeader = MenuItemHelper::isHeader($item);
    $isSubmenu = MenuItemHelper::isSubmenu($item);
    $isActive = ! empty($item['active']);
@endphp

@if ($isHeader)
    <li class="nav-header">{{ $item['header'] }}</li>
@elseif ($isSubmenu)
    <li class="nav-item {{ $isActive ? 'menu-open' : '' }}">
        <a href="#" class="nav-link {{ $isActive ? 'active' : '' }}" role="button" aria-expanded="{{ $isActive ? 'true' : 'false' }}">
            <i class="nav-icon {{ $item['icon'] ?? 'bi bi-circle' }} {{ isset($item['icon_color']) ? 'text-'.$item['icon_color'] : '' }}" aria-hidden="true"></i>
            <p>
                {{ $item['text'] }}
                <i class="nav-arrow bi bi-chevron-right" aria-hidden="true"></i>
            </p>
        </a>
        <ul class="nav nav-treeview">
            @foreach ($item['submenu'] as $child)
                @include('adminlte::partials.menu-item', ['item' => $child])
            @endforeach
        </ul>
    </li>
@elseif (! empty($item['logout']))
    <li class="nav-item edonate-sidebar-logout-form">
        <a href="{{ $item['href'] ?? '#' }}" class="nav-link" data-logout-confirm>
            <i class="nav-icon {{ $item['icon'] ?? 'bi bi-box-arrow-right' }}" aria-hidden="true"></i>
            <p>{{ $item['text'] }}</p>
        </a>
    </li>
@else
    <li class="nav-item">
        <a href="{{ $item['href'] ?? '#' }}" class="nav-link {{ $isActive ? 'active' : '' }}" @isset($item['target']) target="{{ $item['target'] }}" rel="noopener" @endisset>
            <i class="nav-icon {{ $item['icon'] ?? 'bi bi-circle' }} {{ isset($item['icon_color']) ? 'text-'.$item['icon_color'] : '' }}" aria-hidden="true"></i>
            <p>
                {{ $item['text'] }}
                @isset($item['label'])
                    <span class="nav-badge badge text-bg-{{ $item['label_color'] ?? 'danger' }} me-3">{{ $item['label'] }}</span>
                @endisset
            </p>
        </a>
    </li>
@endif
