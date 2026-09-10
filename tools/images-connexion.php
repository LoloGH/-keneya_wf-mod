<?php

/**
 * Les trois tailles de la scene de connexion, derivees d'un seul fichier.
 *
 * `public/images/login-scene-source.png` est le master : la scene telle
 * qu'elle a ete livree, sans aucun texte incruste. Le titre, les six entrees
 * et les mentions sont du texte HTML depuis la v3.3.2, ce qui les rend
 * lisibles par un lecteur d'ecran, traduisibles, et modifiables sans repasser
 * par l'image.
 *
 * Ce script existe pour que personne n'ait a redecouvrir comment les trois
 * JPEG ont ete fabriques le jour ou la scene changera :
 *
 *     docker compose exec app php tools/images-connexion.php
 *
 * Il ecrase les trois fichiers derives et ne touche jamais au master.
 */
$racine = dirname(__DIR__).'/public/images';
$source = $racine.'/login-scene-source.png';

if (! is_file($source)) {
    fwrite(STDERR, "Master introuvable : $source\n");
    exit(1);
}

$master = imagecreatefrompng($source);

if (! $master) {
    fwrite(STDERR, "Le master n'est pas une image PNG lisible.\n");
    exit(1);
}

// Le PNG porte un canal alpha ; un JPEG n'en a pas. Sans fond blanc explicite,
// les zones transparentes ressortiraient en noir.
$largeurMaster = imagesx($master);
$hauteurMaster = imagesy($master);

$tailles = [
    // fichier                     largeur  qualite
    'login-scene.jpg' => [$largeurMaster, 84],
    'login-scene-tablette.jpg' => [1200, 82],
    'login-scene-mobile.jpg' => [780, 80],
];

foreach ($tailles as $fichier => [$largeur, $qualite]) {
    $hauteur = (int) round($hauteurMaster * $largeur / $largeurMaster);

    $cible = imagecreatetruecolor($largeur, $hauteur);
    imagefill($cible, 0, 0, imagecolorallocate($cible, 255, 255, 255));
    imagecopyresampled($cible, $master, 0, 0, 0, 0, $largeur, $hauteur, $largeurMaster, $hauteurMaster);

    imagejpeg($cible, $racine.'/'.$fichier, $qualite);
    imagedestroy($cible);

    printf("%-28s %5d x %4d  %6.0f Ko\n", $fichier, $largeur, $hauteur, filesize($racine.'/'.$fichier) / 1024);
}

imagedestroy($master);
