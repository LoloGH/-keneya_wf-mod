@props(['name' => 'dot'])

@php
    /**
     * Jeu d'icônes en ligne (traits SVG), sans dépendance externe.
     * Chaque icône est décorative : elle est masquée aux lecteurs
     * d'écran, le libellé textuel voisin portant le sens (§48).
     */
    $paths = [
        'home' => '<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V20a1 1 0 0 0 1 1h4v-6h4v6h4a1 1 0 0 0 1-1V9.5"/>',
        'users' => '<circle cx="9" cy="8" r="3.2"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><path d="M16 5.2a3.2 3.2 0 0 1 0 6"/><path d="M17.5 14.2A6 6 0 0 1 21.5 20"/>',
        'stethoscope' => '<path d="M6 3v5a4 4 0 0 0 8 0V3"/><path d="M6 3H4.5M14 3h1.5"/><path d="M10 16v1a4 4 0 0 0 8 0v-2"/><circle cx="18" cy="12" r="2"/>',
        'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>',
        'flask' => '<path d="M9 3v6.2L4.2 18A2 2 0 0 0 6 21h12a2 2 0 0 0 1.8-3L15 9.2V3"/><path d="M8 3h8M6.5 14h11"/>',
        'scan' => '<path d="M3 8V5a2 2 0 0 1 2-2h3M21 8V5a2 2 0 0 0-2-2h-3M3 16v3a2 2 0 0 0 2 2h3M21 16v3a2 2 0 0 1-2 2h-3"/><path d="M7 12h10"/>',
        'bed' => '<path d="M3 20V8M3 12h18a0 0 0 0 1 0 0v8"/><path d="M3 16h18"/><circle cx="7.5" cy="9.5" r="1.8"/>',
        'pill' => '<rect x="2.5" y="8" width="19" height="8" rx="4" transform="rotate(-45 12 12)"/><path d="M9 9l6 6"/>',
        'document' => '<path d="M14 3v5h5"/><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M9 13h6M9 17h4"/>',
        'chat' => '<path d="M21 12a8 8 0 0 1-11.6 7.1L4 20l1-4.4A8 8 0 1 1 21 12z"/>',
        'shield' => '<path d="M12 3l7 3v6c0 4.2-2.8 7.6-7 9-4.2-1.4-7-4.8-7-9V6z"/><path d="m9.5 12 1.8 1.8L15 10"/>',
        'clipboard' => '<rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 4V3h6v1"/><path d="M9 10h6M9 14h6M9 18h3"/>',
        'cog' => '<circle cx="12" cy="12" r="3"/><path d="M12 2v2.5M12 19.5V22M2 12h2.5M19.5 12H22M4.9 4.9l1.8 1.8M17.3 17.3l1.8 1.8M19.1 4.9l-1.8 1.8M6.7 17.3l-1.8 1.8"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
        'bell' => '<path d="M18 8a6 6 0 1 0-12 0c0 6-2 7-2 7h16s-2-1-2-7"/><path d="M10.5 20a2 2 0 0 0 3 0"/>',
        'close' => '<path d="M6 6l12 12M18 6 6 18"/>',
        'menu' => '<path d="M4 7h16M4 12h16M4 17h16"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'alert' => '<path d="M12 3 2.5 20h19z"/><path d="M12 10v4M12 17.5v.01"/>',
        'check' => '<path d="m5 12.5 4.5 4.5L19 7"/>',
        'download' => '<path d="M12 4v11"/><path d="m7.5 11 4.5 4.5 4.5-4.5"/><path d="M5 20h14"/>',
        'print' => '<path d="M7 8V3h10v5"/><rect x="4" y="8" width="16" height="8" rx="2"/><path d="M7 14h10v7H7z"/>',
        'heart' => '<path d="M12 20s-7-4.5-7-9.5A3.9 3.9 0 0 1 12 8a3.9 3.9 0 0 1 7 2.5C19 15.5 12 20 12 20z"/>',
        'chevron-right' => '<path d="m9 5 7 7-7 7"/>',
        'chevron-left' => '<path d="m15 5-7 7 7 7"/>',
        'arrow-left' => '<path d="M19 12H5"/><path d="m11 6-6 6 6 6"/>',
        'logout' => '<path d="M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3"/><path d="M10 8 6 12l4 4"/><path d="M6 12h9"/>',
        'trash' => '<path d="M4 7h16M9 7V5h6v2M6 7l1 13h10l1-13"/>',
        'copy' => '<rect x="9" y="9" width="12" height="12" rx="2"/><path d="M5 15V5a2 2 0 0 1 2-2h8"/>',
        'mail' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m4 7 8 6 8-6"/>',
        'lock' => '<rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/>',
        'eye' => '<path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12z"/><circle cx="12" cy="12" r="3"/>',
        'eye-off' => '<path d="M3 3l18 18"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/><path d="M6.6 6.6C4 8.3 2.5 12 2.5 12s3.5 6.5 9.5 6.5a10.4 10.4 0 0 0 4.1-.8M10.6 5.6A10.9 10.9 0 0 1 12 5.5c6 0 9.5 6.5 9.5 6.5a16.4 16.4 0 0 1-3.4 4.3"/>',
        'globe' => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.5 2.5 3.8 5.7 3.8 9s-1.3 6.5-3.8 9c-2.5-2.5-3.8-5.7-3.8-9s1.3-6.5 3.8-9z"/>',
        'chevron-down' => '<path d="m5 9 7 7 7-7"/>',
        'login' => '<path d="M9 4H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h3"/><path d="M14 8l4 4-4 4"/><path d="M18 12H9"/>',
        'bolt' => '<path d="M13 2 4 14h6l-1 8 9-12h-6l1-8z"/>',
        'dot' => '<circle cx="12" cy="12" r="4"/>',
    ];
@endphp

<svg {{ $attributes->merge(['class' => 'h-5 w-5']) }} viewBox="0 0 24 24" fill="none"
     stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"
     aria-hidden="true" focusable="false">
    {!! $paths[$name] ?? $paths['dot'] !!}
</svg>
