<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light dark">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="robots" content="noindex,nofollow">
<title>@yield('title', 'Dashboard') · Metribay</title>
<script>
try { var mbTheme = localStorage.getItem('metribay.theme') || 'system'; document.documentElement.dataset.theme = mbTheme === 'system' ? (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light') : mbTheme; document.documentElement.dataset.density = localStorage.getItem('metribay.density') || 'comfortable'; } catch (_) {}
</script>
<link rel="icon" href="{{ asset('vendor/metribay/metribay-light.svg') }}" type="image/svg+xml" media="(prefers-color-scheme: light)">
<link rel="icon" href="{{ asset('vendor/metribay/metribay-dark.svg') }}" type="image/svg+xml" media="(prefers-color-scheme: dark)">
<link rel="stylesheet" href="{{ asset('vendor/metribay/app.css') }}?v={{ is_file(public_path('vendor/metribay/app.css')) ? filemtime(public_path('vendor/metribay/app.css')) : 'dev' }}">
@livewireStyles
@stack('head')
