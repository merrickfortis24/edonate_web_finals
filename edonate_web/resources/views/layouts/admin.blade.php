@extends('adminlte::page', [
    'title' => trim($__env->yieldContent('title')) ?: 'eDonate Admin Portal',
])

@php
    $adminPageClass = trim($__env->yieldContent('admin_page_class'));
    $headerTitle = trim($__env->yieldContent('header_title')) ?: 'eDonate Admin Portal';
    $headerSubtitle = trim($__env->yieldContent('header_subtitle'));
    $headerActions = trim($__env->yieldContent('header_actions'));
    $adminPageData = trim($__env->yieldContent('admin_page_data'));
@endphp

@section('content_header')
    <div class="edonate-content-header d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
        <div class="min-w-0">
            <h1 class="h3 mb-1 text-danger-emphasis">{{ $headerTitle }}</h1>
            @if ($headerSubtitle !== '')
                <p class="mb-0 text-body-secondary">{{ $headerSubtitle }}</p>
            @endif
        </div>

        @if ($headerActions !== '')
            <div class="edonate-content-actions d-flex flex-wrap align-items-center gap-2">
                {!! $headerActions !!}
            </div>
        @endif
    </div>
@stop

@section('content')
    <div class="edonate-admin-page {{ $adminPageClass }}">
        @yield('main_content')
    </div>
@stop

@push('css')
    @stack('admin_head')
@endpush

@push('js')
    <script type="application/json" id="adminPageData">{!! $adminPageData !== '' ? $adminPageData : '{}' !!}</script>
    <script>
        (function () {
            var payloadElement = document.getElementById('adminPageData');

            try {
                window.AdminPageData = JSON.parse(payloadElement ? payloadElement.textContent || '{}' : '{}');
            } catch (error) {
                console.warn('Invalid admin page JSON payload.', error);
                window.AdminPageData = {};
            }
        })();
    </script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    @stack('admin_scripts')
@endpush
