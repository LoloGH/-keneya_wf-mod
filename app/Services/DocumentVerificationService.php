<?php

namespace App\Services;

use App\Support\DocumentVerificationResult;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Keneya\Dme\Dme;
use Keneya\Dme\Models\CareOrder;
use Keneya\Dme\Models\Consultation;
use Keneya\Dme\Models\Hospitalization;
use Keneya\Dme\Models\ImagingOrder;
use Keneya\Dme\Models\LabOrder;
use Keneya\Dme\Models\MedicalDocument;
use Keneya\Dme\Models\Patient;
use Keneya\Dme\Models\Prescription;

/**
 * Verification publique d'une reference du dossier medical, depuis le QR
 * code imprime sur chaque PDF genere par le module (PdfGenerator).
 *
 * Le module DME sait produire ces references (IdentifierGenerator) mais ne
 * s'expose lui-meme que derriere une session authentifiee : aucune de ses
 * routes ne convient a un visiteur qui vient de scanner un code. Cette
 * resolution vit donc cote WorkFlow, seul a servir une page publique.
 *
 * Le prefixe de la reference (avant le premier tiret) designe le type, et
 * le type designe a la fois le modele et sa colonne d'identifiant. Cette
 * derniere n'est jamais supposee : elle se lit sur le modele via
 * `identifierColumn()` (trait HasBusinessIdentifier), exactement comme le
 * module la resout lui-meme a la creation. C'est ce qui permet a LAB et IMG,
 * qui partagent tous deux `order_number`, de ne jamais se confondre : seul
 * le prefixe dit dans quelle table chercher.
 */
class DocumentVerificationService
{
    /**
     * Correspondance prefixe -> modele verifiable.
     *
     * La cle est celle de config('dme.identifiers.prefixes') : c'est elle
     * qui donne le prefixe reellement configure, jamais suppose en dur.
     * `date` est la colonne pertinente pour dater ce type de document ; elle
     * differe d'un type a l'autre par nature (une ordonnance est datee de son
     * emission, une hospitalisation de son admission), mais chacune existe
     * deja sur le modele et n'a rien d'invente ici.
     *
     * @var array<string, array{model: class-string<Model>, label: string, date: string}>
     */
    private const TYPES = [
        'patient' => ['model' => Patient::class, 'label' => 'Patient', 'date' => 'created_at'],
        'consultation' => ['model' => Consultation::class, 'label' => 'Consultation', 'date' => 'started_at'],
        'prescription' => ['model' => Prescription::class, 'label' => 'Ordonnance', 'date' => 'issued_on'],
        'lab_order' => ['model' => LabOrder::class, 'label' => 'Demande de laboratoire', 'date' => 'requested_at'],
        'imaging_order' => ['model' => ImagingOrder::class, 'label' => "Demande d'imagerie", 'date' => 'requested_at'],
        'hospitalization' => ['model' => Hospitalization::class, 'label' => 'Hospitalisation', 'date' => 'admitted_at'],
        'care_order' => ['model' => CareOrder::class, 'label' => 'Soin', 'date' => 'starts_at'],
        'document' => ['model' => MedicalDocument::class, 'label' => 'Document', 'date' => 'created_at'],
    ];

    public function verify(string $reference): DocumentVerificationResult
    {
        $reference = strtoupper(trim($reference));

        if (! preg_match('/^([A-Z]+)-\d{4}-\d+$/', $reference, $correspondances)) {
            return DocumentVerificationResult::invalid();
        }

        $cle = $this->typePourPrefixe($correspondances[1]);

        if ($cle === null) {
            return DocumentVerificationResult::invalid();
        }

        $type = self::TYPES[$cle];
        $modele = $type['model'];
        $colonne = (new $modele)->identifierColumn();

        /** @var Model|null $enregistrement */
        $enregistrement = $modele::query()->where($colonne, $reference)->first();

        if ($enregistrement === null) {
            return DocumentVerificationResult::invalid();
        }

        return DocumentVerificationResult::valid(
            type: $type['label'],
            reference: $reference,
            facility: Dme::facility()['name'] ?? null,
            date: $enregistrement->{$type['date']},
            status: $this->libelleStatut($enregistrement),
        );
    }

    /**
     * La cle de config('dme.identifiers.prefixes') dont la valeur configuree
     * correspond a ce prefixe, ou nul si aucune ne correspond (prefixe
     * inconnu, ou reserve a un type non verifiable publiquement).
     */
    private function typePourPrefixe(string $prefixe): ?string
    {
        foreach ((array) config('dme.identifiers.prefixes') as $cle => $valeur) {
            if (is_string($valeur) && strcasecmp($valeur, $prefixe) === 0 && array_key_exists($cle, self::TYPES)) {
                return $cle;
            }
        }

        return null;
    }

    /**
     * Le libelle francais du statut, lu dans le dictionnaire `STATUSES` du
     * modele quand il en tient un ; a defaut, la valeur brute mise en forme.
     */
    private function libelleStatut(Model $enregistrement): ?string
    {
        $statut = $enregistrement->getAttribute('status');

        if (! is_string($statut) || $statut === '') {
            return null;
        }

        $classe = $enregistrement::class;

        if (defined("{$classe}::STATUSES") && array_key_exists($statut, $classe::STATUSES)) {
            return $classe::STATUSES[$statut];
        }

        return Str::of($statut)->replace('_', ' ')->ucfirst()->toString();
    }
}
