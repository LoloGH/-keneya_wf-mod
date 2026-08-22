<?php

/**
 * Messages de validation en francais.
 *
 * L'interface de KEneYa WorkFlow est entierement en francais, messages
 * d'erreur compris : le personnel ne doit jamais voir de texte en anglais.
 */
return [

    'accepted' => 'Le champ :attribute doit etre accepte.',
    'active_url' => "Le champ :attribute n'est pas une URL valide.",
    'after' => 'Le champ :attribute doit etre une date posterieure au :date.',
    'after_or_equal' => 'Le champ :attribute doit etre une date posterieure ou egale au :date.',
    'alpha' => 'Le champ :attribute ne peut contenir que des lettres.',
    'alpha_dash' => 'Le champ :attribute ne peut contenir que des lettres, des chiffres, des tirets et des underscores.',
    'alpha_num' => 'Le champ :attribute ne peut contenir que des lettres et des chiffres.',
    'array' => 'Le champ :attribute doit etre une liste.',
    'before' => 'Le champ :attribute doit etre une date anterieure au :date.',
    'before_or_equal' => 'Le champ :attribute doit etre une date anterieure ou egale au :date.',
    'between' => [
        'array' => 'Le champ :attribute doit contenir entre :min et :max elements.',
        'file' => 'Le champ :attribute doit peser entre :min et :max kilo-octets.',
        'numeric' => 'Le champ :attribute doit etre compris entre :min et :max.',
        'string' => 'Le champ :attribute doit contenir entre :min et :max caracteres.',
    ],
    'boolean' => 'Le champ :attribute doit etre vrai ou faux.',
    'confirmed' => 'La confirmation du champ :attribute ne correspond pas.',
    'current_password' => 'Le mot de passe est incorrect.',
    'date' => "Le champ :attribute n'est pas une date valide.",
    'date_equals' => 'Le champ :attribute doit etre une date egale au :date.',
    'date_format' => 'Le champ :attribute ne correspond pas au format :format.',
    'different' => 'Les champs :attribute et :other doivent etre differents.',
    'digits' => 'Le champ :attribute doit contenir :digits chiffres.',
    'digits_between' => 'Le champ :attribute doit contenir entre :min et :max chiffres.',
    'email' => 'Le champ :attribute doit etre une adresse e-mail valide.',
    'ends_with' => 'Le champ :attribute doit se terminer par une des valeurs suivantes : :values.',
    'exists' => 'La valeur selectionnee pour :attribute est invalide.',
    'file' => 'Le champ :attribute doit etre un fichier.',
    'filled' => 'Le champ :attribute doit avoir une valeur.',
    'gt' => [
        'array' => 'Le champ :attribute doit contenir plus de :value elements.',
        'file' => 'Le champ :attribute doit peser plus de :value kilo-octets.',
        'numeric' => 'Le champ :attribute doit etre superieur a :value.',
        'string' => 'Le champ :attribute doit contenir plus de :value caracteres.',
    ],
    'gte' => [
        'array' => 'Le champ :attribute doit contenir au moins :value elements.',
        'file' => 'Le champ :attribute doit peser au moins :value kilo-octets.',
        'numeric' => 'Le champ :attribute doit etre superieur ou egal a :value.',
        'string' => 'Le champ :attribute doit contenir au moins :value caracteres.',
    ],
    'image' => 'Le champ :attribute doit etre une image.',
    'in' => 'La valeur selectionnee pour :attribute est invalide.',
    'integer' => 'Le champ :attribute doit etre un nombre entier.',
    'lt' => [
        'array' => 'Le champ :attribute doit contenir moins de :value elements.',
        'file' => 'Le champ :attribute doit peser moins de :value kilo-octets.',
        'numeric' => 'Le champ :attribute doit etre inferieur a :value.',
        'string' => 'Le champ :attribute doit contenir moins de :value caracteres.',
    ],
    'lte' => [
        'array' => 'Le champ :attribute doit contenir au plus :value elements.',
        'file' => 'Le champ :attribute doit peser au plus :value kilo-octets.',
        'numeric' => 'Le champ :attribute doit etre inferieur ou egal a :value.',
        'string' => 'Le champ :attribute doit contenir au plus :value caracteres.',
    ],
    'max' => [
        'array' => 'Le champ :attribute ne peut pas contenir plus de :max elements.',
        'file' => 'Le champ :attribute ne peut pas peser plus de :max kilo-octets.',
        'numeric' => 'Le champ :attribute ne peut pas depasser :max.',
        'string' => 'Le champ :attribute ne peut pas depasser :max caracteres.',
    ],
    'min' => [
        'array' => 'Le champ :attribute doit contenir au moins :min elements.',
        'file' => 'Le champ :attribute doit peser au moins :min kilo-octets.',
        'numeric' => 'Le champ :attribute doit valoir au moins :min.',
        'string' => 'Le champ :attribute doit contenir au moins :min caracteres.',
    ],
    'not_in' => 'La valeur selectionnee pour :attribute est invalide.',
    'numeric' => 'Le champ :attribute doit etre un nombre.',
    'present' => 'Le champ :attribute doit etre present.',
    'regex' => 'Le format du champ :attribute est invalide.',
    'required' => 'Le champ :attribute est obligatoire.',
    'required_if' => 'Le champ :attribute est obligatoire quand :other vaut :value.',
    'required_with' => 'Le champ :attribute est obligatoire quand :values est renseigne.',
    'required_without' => "Le champ :attribute est obligatoire quand :values n'est pas renseigne.",
    'same' => 'Les champs :attribute et :other doivent etre identiques.',
    'size' => [
        'array' => 'Le champ :attribute doit contenir :size elements.',
        'file' => 'Le champ :attribute doit peser :size kilo-octets.',
        'numeric' => 'Le champ :attribute doit valoir :size.',
        'string' => 'Le champ :attribute doit contenir :size caracteres.',
    ],
    'starts_with' => 'Le champ :attribute doit commencer par une des valeurs suivantes : :values.',
    'string' => 'Le champ :attribute doit etre une chaine de caracteres.',
    'unique' => 'Cette valeur de :attribute est deja utilisee.',
    'uploaded' => 'Le televersement du champ :attribute a echoue.',
    'url' => 'Le format du champ :attribute est invalide.',

    'custom' => [
        'attribute-name' => [
            'rule-name' => 'custom-message',
        ],
    ],

    'attributes' => [
        'age' => 'age',
        'crno' => 'numero de dossier papier',
        'email' => 'adresse e-mail',
        'gender' => 'sexe',
        'instructions' => 'instructions',
        'kind' => 'type de service',
        'mobile' => 'telephone',
        'name' => 'nom',
        'password' => 'mot de passe',
        'phone' => 'telephone',
        'reason' => 'motif',
        'resultText' => 'resultat',
        'service_id' => 'service',
        'toServiceId' => 'service destinataire',
    ],

];
