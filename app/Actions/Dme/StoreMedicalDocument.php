<?php

namespace App\Actions\Dme;

use App\Actions\Dme\Concerns\ResolvesMedicalRecord;
use App\Models\Doctor;
use App\Models\StaffMember;
use App\Models\Visit;
use App\Support\Audit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Keneya\Dme\Models\MedicalDocument;
use Keneya\Dme\Services\Documents\DocumentStorage;

/**
 * Document verse au dossier medical depuis /service (v3.3.1).
 *
 * A distinguer des pieces jointes de WorkFlow, qui accompagnent un renvoi et
 * circulent avec le patient : ici, un compte rendu d'echographie rapporte de
 * l'exterieur, un resultat d'analyse, un certificat, ce qui a vocation a
 * rester au dossier.
 *
 * Deux tables, un seul disque : c'est la decision du chantier. L'operationnel
 * et le dossier medical ne sont pas le meme objet ni les memes droits, mais
 * les fichiers n'ont aucune raison d'etre stockes deux fois, ni sauvegardes
 * deux fois.
 *
 * L'ecriture passe par `DocumentStorage` du module et non par un `put()`
 * direct : c'est lui qui construit le chemin, calcule l'empreinte du fichier
 * et pose la visibilite privee. Un document medical n'est jamais servi depuis
 * le systeme de fichiers, toujours par une route controlee.
 */
class StoreMedicalDocument
{
    use ResolvesMedicalRecord;

    public function __construct(private readonly DocumentStorage $storage) {}

    /** Taille maximale, alignee sur celle que le module declare. */
    public function maxSizeKb(): int
    {
        return (int) config('dme.documents.max_size_kb', 20480);
    }

    /** @return array<int, string> */
    public function allowedExtensions(): array
    {
        return (array) config('dme.documents.allowed_mimes', ['pdf', 'jpg', 'jpeg', 'png']);
    }

    /**
     * `$source` rattache le document a l'acte qu'il documente : une demande
     * d'analyses, un examen d'imagerie. Sans lui, un compte rendu se retrouve
     * bien au dossier, mais la fiche de l'examen l'ignore : le medecin voit sa
     * demande sans le resultat qui l'a close. Le module cherche ses documents
     * par cette provenance.
     */
    public function execute(
        Visit $visit,
        Doctor|StaffMember $doctor,
        UploadedFile $fichier,
        string $type,
        ?string $titre = null,
        ?Model $source = null,
    ): MedicalDocument {
        $dossier = $this->dossierMedical($visit);

        $document = $this->storage->storeUploaded($dossier, $fichier, array_filter([
            'type' => $type,
            'title' => $titre,
            'uploaded_by' => $doctor->user_id,
            'source_type' => $source !== null ? $source::class : null,
            'source_id' => $source?->getKey(),
        ], static fn ($valeur) => $valeur !== null));

        Audit::log(
            Audit::EVENT_MEDICAL_DOCUMENT,
            sprintf(
                'Document « %s » verse au dossier de %s par %s.',
                $document->title,
                $visit->patient->patient_code,
                $doctor->name(),
            ),
            $visit,
        );

        return $document;
    }
}
