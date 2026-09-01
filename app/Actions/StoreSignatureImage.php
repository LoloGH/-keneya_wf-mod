<?php

namespace App\Actions;

use App\Models\Doctor;
use App\Models\Setting;
use App\Support\Audit;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * Depot d'une signature ou d'un tampon (v3.2.9, point 2).
 *
 * Trois images, un seul chemin de code : signature du medecin, tampon du
 * medecin, tampon de l'etablissement. Elles partagent les memes exigences —
 * image uniquement, taille bornee, et toute modification tracee.
 *
 * Les controles sont refaits ici, cote serveur, comme pour les pieces jointes :
 * un formulaire se contourne, pas une action. Et ces trois images-la portent
 * une valeur legale — apposer la signature d'un praticien sur une ordonnance
 * n'est pas un geste anodin.
 */
class StoreSignatureImage
{
    /** 2 Mo : largement de quoi scanner une signature, trop peu pour un abus. */
    public const TAILLE_MAX = 2 * 1024 * 1024;

    /**
     * Formats acceptes, verifies sur le type reel du fichier et non sur son
     * extension. Pas de SVG : il peut porter du script.
     *
     * @var array<int, string>
     */
    public const TYPES_ACCEPTES = ['image/png', 'image/jpeg', 'image/webp'];

    public function forDoctorSignature(UploadedFile $file, Doctor $doctor): string
    {
        return $this->poser(
            $file,
            $doctor,
            'signature_path',
            sprintf('Signature du medecin %s modifiee.', $doctor->name()),
        );
    }

    public function forDoctorStamp(UploadedFile $file, Doctor $doctor): string
    {
        return $this->poser(
            $file,
            $doctor,
            'stamp_path',
            sprintf('Tampon du medecin %s modifie.', $doctor->name()),
        );
    }

    /**
     * Le tampon de l'etablissement vit dans `settings`, pas sur une fiche : il
     * vaut pour tous les praticiens.
     */
    public function forHospitalStamp(UploadedFile $file): string
    {
        $this->assertAcceptable($file);

        $ancien = Setting::get(Setting::HOSPITAL_STAMP_PATH);
        $chemin = $this->ranger($file, 'etablissement');

        Setting::put(Setting::HOSPITAL_STAMP_PATH, $chemin);
        $this->oublier($ancien);

        Audit::log(Audit::EVENT_SIGNATURE_CHANGED, "Tampon de l'etablissement modifie.");

        return $chemin;
    }

    private function poser(UploadedFile $file, Doctor $doctor, string $colonne, string $trace): string
    {
        $this->assertAcceptable($file);

        $ancien = $doctor->{$colonne};
        $chemin = $this->ranger($file, 'medecins/'.$doctor->getKey());

        $doctor->forceFill([$colonne => $chemin])->save();
        $this->oublier($ancien);

        Audit::log(Audit::EVENT_SIGNATURE_CHANGED, $trace, $doctor);

        return $chemin;
    }

    private function ranger(UploadedFile $file, string $dossier): string
    {
        $chemin = $file->store($dossier, 'signatures');

        if ($chemin === false) {
            throw new InvalidArgumentException("L'image n'a pas pu etre enregistree.");
        }

        return $chemin;
    }

    /**
     * L'image remplacee est effacee du disque : une signature perimee qui
     * traine reste une signature utilisable.
     */
    private function oublier(?string $chemin): void
    {
        if (filled($chemin)) {
            Storage::disk('signatures')->delete($chemin);
        }
    }

    private function assertAcceptable(UploadedFile $file): void
    {
        if ($file->getSize() > self::TAILLE_MAX) {
            throw new InvalidArgumentException('L\'image ne doit pas depasser 2 Mo.');
        }

        if (! in_array((string) $file->getMimeType(), self::TYPES_ACCEPTES, true)) {
            throw new InvalidArgumentException('Seules les images PNG, JPEG ou WebP sont acceptees.');
        }
    }
}
