<svg class="icon {{ $iconClass ?? '' }}" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
@switch($name)
@case('overview') <rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><path d="M14 20v-6m3 6v-3m3 3v-8"/> @break
@case('settings') <path d="M4 7h16M4 17h16"/><circle cx="9" cy="7" r="3"/><circle cx="16" cy="17" r="3"/> @break
@case('user') <circle cx="12" cy="8" r="4"/><path d="M4 21v-2a8 8 0 0 1 16 0v2"/> @break
@case('layers') <path d="m12 3 10 6-10 6L2 9l10-6Zm-9 12 9 6 9-6M3 12l9 6 9-6"/> @break
@case('logout') <path d="M10 4H4v16h6m4-12 4 4-4 4m-6-4h13"/> @break
@case('chevrons') <path d="m8 9 4-4 4 4m-8 6 4 4 4-4"/> @break
@case('chevron') <path d="m9 5 7 7-7 7"/> @break
@case('search') <circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/> @break
@case('plus') <path d="M12 5v14M5 12h14"/> @break
@case('arrow-up-right') <path d="M7 17 17 7M7 7h10v10"/> @break
@case('arrow-left') <path d="m10 5-7 7 7 7m-6-7h17"/> @break
@case('arrow-down') <path d="M12 3v18m-7-7 7 7 7-7"/> @break
@case('arrow-up') <path d="M12 21V3m-7 7 7-7 7 7"/> @break
@case('refresh') <path d="M20 7V3m0 4h-4M4 17v4m0-4h4"/><path d="M20 7a9 9 0 0 0-16 1m0 9a9 9 0 0 0 16-1"/> @break
@case('clock') <circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/> @break
@case('check') <path d="m5 12 4 4L19 6"/> @break
@case('alert') <path d="m12 3 10 18H2L12 3Z"/><path d="M12 9v4m0 4h.01"/> @break
@case('info') <circle cx="12" cy="12" r="9"/><path d="M12 11v6m0-10h.01"/> @break
@case('link') <path d="m10 13 4-4M8 16l-2 2a4 4 0 0 1-6-6l5-5a4 4 0 0 1 6 0m2 1 2-2a4 4 0 0 1 6 6l-5 5a4 4 0 0 1-6 0"/> @break
@case('globe') <circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a19 19 0 0 1 0 18 19 19 0 0 1 0-18Z"/> @break
@case('chart') <path d="M4 3v17h17M7 14l4-4 4 3 6-8"/> @break
@case('key') <circle cx="8" cy="9" r="5"/><path d="m12 13 8 8m-3-3 3-3m-6 0 3-3"/> @break
@case('download') <path d="M12 3v12m-5-5 5 5 5-5M4 16v5h16v-5"/> @break
@case('upload') <path d="M12 15V3m-5 5 5-5 5 5M4 16v5h16v-5"/> @break
@case('note') <path d="M14 3H4v18h16V9l-6-6Zm0 0v6h6M8 13h8m-8 4h5"/> @break
@case('close') <path d="m6 6 12 12M6 18 18 6"/> @break
@case('sun') <circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M2 12h2m16 0h2M5 5l1 1m12 12 1 1M5 19l1-1M18 6l1-1"/> @break
@case('moon') <path d="M20 15a8 8 0 0 1-11-11 9 9 0 1 0 11 11Z"/> @break
@case('folder') <path d="M3 6a2 2 0 0 1 2-2h5l2 3h7a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6Z"/> @break
@case('lock') <rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V6a4 4 0 0 1 8 0v4m-4 5v2"/> @break
@case('mail') <rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 6 9 7 9-7"/> @break
@case('tag') <path d="M3 12V4.5A1.5 1.5 0 0 1 4.5 3H12l9 9-9 9-9-9z"/><circle cx="7.5" cy="7.5" r="1.5"/> @break
@case('repeat') <path d="M17 2l3 3-3 3"/><path d="M4 11V9a4 4 0 0 1 4-4h12"/><path d="M7 22l-3-3 3-3"/><path d="M20 13v2a4 4 0 0 1-4 4H4"/> @break
@case('eye') <path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/> @break
@case('eye-off') <path d="M10.6 5.1A9.7 9.7 0 0 1 12 5c6 0 10 7 10 7a17 17 0 0 1-2.9 3.7M6.6 6.6C3.7 8.5 2 12 2 12s4 7 10 7a9.6 9.6 0 0 0 5.4-1.6"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2M3 3l18 18"/> @break
@default <circle cx="12" cy="12" r="8"/>
@endswitch
</svg>
