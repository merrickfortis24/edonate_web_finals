@php
    $edonateFaviconFile = public_path('images/edonate-icon.png');
    $edonateFaviconVersion = is_file($edonateFaviconFile)
        ? (string) filemtime($edonateFaviconFile)
        : '1';
    $edonateFaviconUrl = asset('images/edonate-icon.png').'?v='.$edonateFaviconVersion;
@endphp
<link rel="icon" type="image/png" href="{{ $edonateFaviconUrl }}">
<link rel="shortcut icon" type="image/png" href="{{ $edonateFaviconUrl }}">
<link rel="apple-touch-icon" href="{{ $edonateFaviconUrl }}">
