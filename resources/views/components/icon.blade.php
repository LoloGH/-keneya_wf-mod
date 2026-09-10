{{-- Jeu d'icones unique de l'application.

     Avant, chaque gabarit ecrivait ses propres <path> : vingt-huit SVG
     disperses, cinq epaisseurs de trait differentes, et rien pour empecher la
     prochaine page d'en inventer une sixieme. Le jeu vit desormais ici, et
     nulle part ailleurs : c'est la seule facon de tenir dans la duree la regle
     « un seul jeu d'icones sur toute l'application ».

     Toutes les icones sont en trait sur une grille de 24, epaisseur 1.75,
     extremites et jonctions arrondies. Elles heritent de la couleur du texte
     (`currentColor`) : une icone ne choisit jamais sa couleur, c'est son
     contexte qui la donne.

     Usage :
         <x-icon name="batiment" />
         <x-icon name="cloche" size="18" class="qqch__icone" />

     Un nom inconnu rend un carre pointille plutot que rien : une icone
     manquante doit se voir au controle visuel, pas disparaitre en silence. --}}
@props(['name', 'size' => 20])

@php
    // Les cles sont en francais, comme le reste du code du projet. Chaque
    // valeur est le contenu du <svg>, sans l'enveloppe.
    $jeu = [
        // --- Navigation : les entrees de la barre laterale -----------------
        'batiment' => '<path d="M6 22V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v18Z"/><path d="M6 12H4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2"/><path d="M18 9h2a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2h-2"/><path d="M10 6h4"/><path d="M10 10h4"/><path d="M10 14h4"/><path d="M10 18h4"/>',
        'services' => '<path d="m6 17 5-5-5-5"/><path d="m13 17 5-5-5-5"/>',
        'personnel' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'lit' => '<path d="M2 4v16"/><path d="M2 8h18a2 2 0 0 1 2 2v10"/><path d="M2 17h20"/><path d="M6 8v9"/>',
        'tarif' => '<circle cx="12" cy="12" r="10"/><path d="M16 8h-6a2 2 0 1 0 0 4h4a2 2 0 1 1 0 4H8"/><path d="M12 6v2"/><path d="M12 16v2"/>',
        'patient' => '<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        'retour' => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/><path d="M12 7v4"/><path d="M12 14h.01"/>',
        'sms' => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
        'pathologie' => '<path d="M22 12h-4l-3 9L9 3l-3 9H2"/>',
        'audit' => '<path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1"/><path d="M12 11h4"/><path d="M12 16h4"/><path d="M8 11h.01"/><path d="M8 16h.01"/>',
        'utilisateurs' => '<path d="M18 21a8 8 0 0 0-12 0"/><circle cx="12" cy="11" r="4"/><circle cx="12" cy="12" r="10"/>',
        'reglages' => '<path d="M4 21v-7"/><path d="M4 10V3"/><path d="M12 21v-9"/><path d="M12 8V3"/><path d="M20 21v-5"/><path d="M20 12V3"/><path d="M1 14h6"/><path d="M9 8h6"/><path d="M17 16h6"/>',
        // Un cylindre de donnees, et non la disquette de « enregistrer » : la
        // section Sauvegarde et le bouton d'enregistrement d'un formulaire ne
        // font pas la meme chose, ils ne peuvent pas porter la meme icone.
        'sauvegarde' => '<ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5v14c0 1.7 4 3 9 3s9-1.3 9-3V5"/><path d="M3 12c0 1.7 4 3 9 3s9-1.3 9-3"/>',
        'suppression' => '<path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M10 11v6"/><path d="M14 11v6"/>',
        'planning' => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h18"/>',
        'file' => '<path d="M3 6h18"/><path d="M7 12h13"/><path d="M11 18h9"/>',
        'soins' => '<path d="M11 2h2a2 2 0 0 1 2 2v2h2a2 2 0 0 1 2 2v2a2 2 0 0 1-2 2h-2v2a2 2 0 0 1-2 2h-2a2 2 0 0 1-2-2v-2H7a2 2 0 0 1-2-2V10a2 2 0 0 1 2-2h2V4a2 2 0 0 1 2-2z"/>',

        // --- Actions et etats ----------------------------------------------
        'recherche' => '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
        'cloche' => '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/>',
        'info' => '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/>',
        'valide' => '<path d="M21.8 10A10 10 0 1 1 17 3.3"/><path d="m9 11 3 3L22 4"/>',
        'alerte' => '<path d="m21.7 18-8-14a2 2 0 0 0-3.4 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.7-3z"/><path d="M12 9v4"/><path d="M12 17h.01"/>',
        'enregistrer' => '<path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><path d="M17 21v-8H7v8"/><path d="M7 3v5h8"/>',
        'reinitialiser' => '<path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/><path d="M8 16H3v5"/>',
        'modifier' => '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/>',
        'ajouter' => '<path d="M12 5v14"/><path d="M5 12h14"/>',
        'retirer' => '<path d="M5 12h14"/>',
        'fermer' => '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>',
        'deconnexion' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/>',
        'chevron' => '<path d="m9 18 6-6-6-6"/>',
        'image' => '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.1-3.1a2 2 0 0 0-2.8 0L6 21"/>',
        // Plateau technique : le cadre de visee du laboratoire et de
        // l'imagerie, reunis sous une seule entree sur l'ecran de connexion.
        'plateau' => '<path d="M3 8V5.5A2.5 2.5 0 0 1 5.5 3H8"/><path d="M16 3h2.5A2.5 2.5 0 0 1 21 5.5V8"/><path d="M21 16v2.5a2.5 2.5 0 0 1-2.5 2.5H16"/><path d="M8 21H5.5A2.5 2.5 0 0 1 3 18.5V16"/><path d="M3.5 12h17"/>',
        'televerser' => '<path d="M12 13v8"/><path d="m8 17 4-4 4 4"/><path d="M20.9 18.4A5 5 0 0 0 18 9h-1.3A8 8 0 1 0 4 16.2"/>',
        'document' => '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7z"/><path d="M14 2v5h5"/><path d="M16 13H8"/><path d="M16 17H8"/><path d="M10 9H8"/>',
        // --- Page de connexion --------------------------------------------
        // Point d'exclamation cercle : l'alerte de la page de connexion. A
        // distinguer du triangle « alerte », qui annonce une consequence a
        // peser ; ici il s'agit d'un identifiant refuse.
        'alerte-cercle' => '<circle cx="12" cy="12" r="9"/><path d="M12 7.5v5"/><path d="M12 16.2h.01"/>',
        'identifiant' => '<circle cx="12" cy="8" r="3.6"/><path d="M4.5 20c.6-3.8 3.7-6 7.5-6s6.9 2.2 7.5 6"/>',
        'cadenas' => '<rect x="4" y="10" width="16" height="10" rx="2"/><path d="M8 10V7.5a4 4 0 0 1 8 0V10"/>',
        'oeil' => '<path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12Z"/><circle cx="12" cy="12" r="3"/>',
        'oeil-barre' => '<path d="M4 4l16 16"/><path d="M9.9 5.8A9.6 9.6 0 0 1 12 5.5c6 0 9.5 6.5 9.5 6.5a17 17 0 0 1-3.3 4.1M6.4 7.9A17 17 0 0 0 2.5 12S6 18.5 12 18.5c1 0 1.9-.2 2.7-.5"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/>',
        'connexion' => '<path d="M14 3h5a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-5"/><path d="M10 17l5-5-5-5"/><path d="M15 12H3"/>',
        'securise' => '<path d="M12 3l7.5 3v5.5c0 4.4-3.1 8.3-7.5 9.5-4.4-1.2-7.5-5.1-7.5-9.5V6Z"/><path d="M8.8 12.2l2.2 2.2 4.2-4.4"/>',
        'rapide' => '<path d="M13 2 4.5 13.5H11l-1 8.5 8.5-11.5H12l1-8.5Z"/>',
        'centralise' => '<circle cx="12" cy="12" r="8.5"/><circle cx="12" cy="12" r="3.5"/><path d="M12 1.5v3M12 19.5v3M1.5 12h3M19.5 12h3"/>',
        'performant' => '<path d="M12 3.5l2.6 5.3 5.9.9-4.2 4.1 1 5.8-5.3-2.8-5.3 2.8 1-5.8-4.2-4.1 5.9-.9Z"/>',
        'etablissement' => '<path d="M4 21V6l7-3 7 3v15"/><path d="M9 21v-5h4v5"/>',

        'bouclier' => '<path d="M20 13c0 5-3.5 7.5-7.7 9a1 1 0 0 1-.6 0C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.2-2.7a1 1 0 0 1 1.6 0C14.5 3.8 17 5 19 5a1 1 0 0 1 1 1z"/>',

        // --- Coordonnees, reprises telles quelles sur l'ordonnance ----------
        'adresse' => '<path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0"/><circle cx="12" cy="10" r="3"/>',
        'telephone' => '<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.5 2.1L8 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.5c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.8 2z"/>',
        'courriel' => '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-9 5.7a2 2 0 0 1-2 0L2 7"/>',
        'site' => '<circle cx="12" cy="12" r="10"/><path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20"/><path d="M2 12h20"/>',
        'horaires' => '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
        'slogan' => '<path d="M19 14c1.5-1.5 3-3.4 3-5.5A5.5 5.5 0 0 0 12 5 5.5 5.5 0 0 0 2 8.5c0 2.1 1.5 4 3 5.5l7 7z"/>',
    ];

    $contenu = $jeu[$name] ?? '<rect x="3" y="3" width="18" height="18" rx="2" stroke-dasharray="3 3"/>';
@endphp

<svg {{ $attributes->merge(['class' => 'icone']) }}
     viewBox="0 0 24 24" width="{{ $size }}" height="{{ $size }}"
     fill="none" stroke="currentColor" stroke-width="1.75"
     stroke-linecap="round" stroke-linejoin="round"
     aria-hidden="true" focusable="false">{!! $contenu !!}</svg>
