<script type="module" src="{{ asset('vendor/metribay/app.js') }}?v={{ is_file(public_path('vendor/metribay/app.js')) ? filemtime(public_path('vendor/metribay/app.js')) : 'dev' }}"></script>
@livewireScripts
@stack('scripts')
