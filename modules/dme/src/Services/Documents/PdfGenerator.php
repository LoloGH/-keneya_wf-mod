<?php

declare(strict_types=1);

namespace Keneya\Dme\Services\Documents;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Keneya\Dme\Dme;
use Keneya\Dme\Models\Consultation;
use Keneya\Dme\Models\Hospitalization;
use Keneya\Dme\Models\LabOrder;
use Keneya\Dme\Models\MedicalDocument;
use Keneya\Dme\Models\Patient;
use Keneya\Dme\Models\Prescription;

/**
 * Génération des documents PDF (§47).
 *
 * Chaque document porte l'établissement, le patient, la date, l'auteur,
 * sa référence métier et un QR code de vérification. Le PDF produit peut
 * être renvoyé au navigateur ou archivé dans le dossier du patient : dans
 * ce dernier cas il devient un MedicalDocument soumis aux mêmes règles
 * d'accès que les documents importés (§42).
 */
class PdfGenerator
{
    public function __construct(
        private readonly DocumentStorage $storage,
        private readonly QrCodeGenerator $qrCodes,
    ) {
    }

    public function prescription(Prescription $prescription): string
    {
        [$view, $data] = $this->prescriptionPayload($prescription);

        return $this->toPdf($view, $data);
    }

    /**
     * La meme ordonnance, rendue en HTML.
     *
     * Une application hote peut vouloir l'imprimer depuis le navigateur
     * plutot que de la telecharger. Elle doit alors sortir de la meme
     * composition, sinon l'etablissement diffuse deux documents differents
     * sous le meme numero : c'est la raison d'etre de cette methode, et non
     * un second gabarit.
     */
    public function prescriptionView(Prescription $prescription): View
    {
        [$view, $data] = $this->prescriptionPayload($prescription);

        return view($view, $this->pourNavigateur($data) + ['autoPrint' => true]);
    }

    /**
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function prescriptionPayload(Prescription $prescription): array
    {
        $prescription->loadMissing(['patient.identifiers', 'doctor', 'items']);

        return $this->payload('dme::pdf.prescription', [
            'prescription' => $prescription,
            'patient' => $prescription->patient,
            'reference' => $prescription->prescription_number,
        ], $prescription);
    }

    public function consultationReport(Consultation $consultation): string
    {
        $consultation->loadMissing(['patient', 'doctor', 'service', 'vitalSigns', 'clinicalNotes', 'diagnoses']);

        return $this->render('dme::pdf.consultation', [
            'consultation' => $consultation,
            'patient' => $consultation->patient,
            'reference' => $consultation->consultation_number,
        ]);
    }

    public function labReport(LabOrder $order): string
    {
        $order->loadMissing(['patient', 'doctor', 'items.results']);

        return $this->render('dme::pdf.lab-report', [
            'order' => $order,
            'patient' => $order->patient,
            'reference' => $order->order_number,
        ]);
    }

    public function dischargeSummary(Hospitalization $hospitalization): string
    {
        $hospitalization->loadMissing(['patient', 'doctor', 'service', 'events']);

        return $this->render('dme::pdf.discharge-summary', [
            'hospitalization' => $hospitalization,
            'patient' => $hospitalization->patient,
            'reference' => $hospitalization->hospitalization_number,
        ]);
    }

    public function patientSummary(Patient $patient): string
    {
        $patient->loadMissing(['allergies', 'chronicConditions', 'medications', 'attendingDoctor']);

        return $this->render('dme::pdf.patient-summary', [
            'patient' => $patient,
            'reference' => $patient->patient_number,
        ]);
    }

    /**
     * Certificat médical libre.
     */
    public function certificate(Patient $patient, string $title, string $content, ?string $reference = null): string
    {
        return $this->render('dme::pdf.certificate', [
            'patient' => $patient,
            'title' => $title,
            'content' => $content,
            'reference' => $reference ?? $patient->patient_number,
        ]);
    }

    /**
     * Archive un PDF déjà produit dans le dossier du patient.
     */
    public function archive(
        Patient $patient,
        string $contents,
        string $title,
        string $type,
        ?object $source = null,
    ): MedicalDocument {
        return $this->storage->storeGenerated($patient, $contents, $title, $type, array_filter([
            'source_type' => $source !== null ? $source::class : null,
            'source_id' => $source?->getKey(),
        ]));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function render(string $view, array $data, mixed $subject = null): string
    {
        return $this->toPdf(...$this->payload($view, $data, $subject));
    }

    /**
     * Complete les donnees communes a tous les documents.
     *
     * `$subject` est l'enregistrement que le document restitue. Il sert a
     * demander a l'hote la signature et les cachets qui l'engagent : ces
     * images lui appartiennent, le module ne fait que les placer.
     *
     * @param  array<string, mixed>  $data
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function payload(string $view, array $data, mixed $subject = null): array
    {
        $reference = (string) ($data['reference'] ?? '');

        $data['facility'] = Dme::facility();
        $data['signatures'] = Dme::signaturesFor($subject);
        $data['generatedAt'] = now();
        $data['qrCode'] = $this->qrCodes->dataUri(
            rtrim((string) config('app.url'), '/').'/documents/verifier/'.$reference
        );

        return [$view, $data];
    }

    /**
     * Rend les images utilisables par un navigateur.
     *
     * DomPDF lit le disque, un navigateur ne le peut pas : la signature, les
     * cachets et le logo arrivent en chemins absolus, ce qui convient au PDF
     * mais donnerait des images cassees à l'écran. Ils sont donc encodés, et
     * seulement pour ce rendu-là, pour ne pas alourdir chaque PDF de leur
     * transcription en base64.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function pourNavigateur(array $data): array
    {
        foreach (['doctorSignature', 'doctorStamp', 'facilityStamp'] as $cle) {
            $data['signatures'][$cle] = $this->dataUri($data['signatures'][$cle] ?? null);
        }

        if (! empty($data['facility']['logo'])) {
            $data['facility']['logo'] = $this->dataUri($data['facility']['logo']);
        }

        return $data;
    }

    /**
     * Un fichier image en `data:` URI, ou null s'il n'est pas lisible.
     *
     * Un fichier illisible vaut image absente : une ordonnance qu'on ne peut
     * plus imprimer serait pire qu'une signature manquante.
     */
    private function dataUri(?string $chemin): ?string
    {
        if ($chemin === null || $chemin === '' || ! is_file($chemin)) {
            return null;
        }

        $type = @mime_content_type($chemin) ?: 'image/png';
        $octets = @file_get_contents($chemin);

        return $octets === false ? null : 'data:'.$type.';base64,'.base64_encode($octets);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function toPdf(string $view, array $data): string
    {
        return Pdf::loadView($view, $data)
            ->setPaper('a4')
            ->output();
    }
}
