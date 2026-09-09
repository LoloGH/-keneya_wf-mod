<?php

namespace App\Livewire\Concerns;

use App\Actions\Dme\OrderLaboratory;
use App\Models\Service;
use App\Models\StaffType;
use Keneya\Dme\Models\ImagingOrder;

/**
 * La demande d'examen qui accompagne un renvoi vers un plateau technique
 * (v3.3.1).
 *
 * Le formulaire ne s'affiche pas de lui-meme : il suit la destination choisie.
 * Le medecin dit ou il envoie le patient, et l'application sait quoi lui
 * demander — l'echographie appelle une demande d'imagerie, le laboratoire une
 * demande d'analyses. C'est le service qui porte cette nature
 * ({@see Service::examKind()}), declaree par l'administrateur.
 *
 * Le trait est partage par les deux files qui savent renvoyer un patient,
 * celle du medecin et celle d'un poste dedie : le formulaire doit etre le meme
 * des deux cotes, sinon deux techniciens recevraient deux demandes
 * differentes pour le meme geste.
 */
trait RequestsExamination
{
    /**
     * Analyses demandees. Une ligne vide est ouverte d'emblee : le medecin
     * n'a pas a cliquer pour commencer a ecrire.
     *
     * @var array<int, array{name: string, category: string}>
     */
    public array $examExams = [self::ANALYSE_VIDE];

    public string $examPriority = 'routine';

    public string $examIndication = '';

    public string $examModality = 'ultrasound';

    public string $examBodySite = '';

    private const ANALYSE_VIDE = ['name' => '', 'category' => ''];

    public function addExamLine(): void
    {
        if (count($this->examExams) >= OrderLaboratory::MAX_ANALYSES) {
            return;
        }

        $this->examExams[] = self::ANALYSE_VIDE;
    }

    public function removeExamLine(int $index): void
    {
        if (count($this->examExams) <= 1) {
            $this->examExams = [self::ANALYSE_VIDE];
            $this->resetValidation();

            return;
        }

        unset($this->examExams[$index]);
        $this->examExams = array_values($this->examExams);
        $this->resetValidation();
    }

    protected function resetExamination(): void
    {
        $this->examExams = [self::ANALYSE_VIDE];
        $this->examPriority = 'routine';
        $this->examIndication = '';
        $this->examModality = 'ultrasound';
        $this->examBodySite = '';
    }

    /**
     * Les regles propres a la demande, vides quand la destination ne realise
     * aucun examen.
     *
     * @return array<string, array<int, string>>
     */
    protected function examinationRules(?Service $toService): array
    {
        return match ($toService?->examKind()) {
            Service::EXAM_LABORATORY => [
                'examPriority' => ['required', 'in:'.implode(',', array_keys(OrderLaboratory::PRIORITIES))],
                'examIndication' => ['nullable', 'string', 'max:1000'],
                'examExams' => ['array', 'max:'.OrderLaboratory::MAX_ANALYSES],
                'examExams.*.name' => ['nullable', 'string', 'max:150'],
                'examExams.*.category' => ['nullable', 'string', 'max:100'],
            ],
            Service::EXAM_IMAGING => [
                // Exigee : elle conditionne l'appareil, la duree et le compte
                // rendu attendu.
                'examModality' => ['required', 'in:'.implode(',', array_keys(ImagingOrder::MODALITIES))],
                'examBodySite' => ['nullable', 'string', 'max:150'],
                'examPriority' => ['required', 'in:'.implode(',', array_keys(OrderLaboratory::PRIORITIES))],
                'examIndication' => ['nullable', 'string', 'max:1000'],
            ],
            default => [],
        };
    }

    /**
     * La demande telle que l'action l'attend, ou nul quand la destination ne
     * realise pas d'examen — le renvoi part alors seul, comme avant.
     *
     * @return array<string, mixed>|null
     */
    protected function examinationPayload(?Service $toService): ?array
    {
        return match ($toService?->examKind()) {
            Service::EXAM_LABORATORY => [
                'requested_at' => now()->toDateTimeString(),
                'priority' => $this->examPriority,
                'indication' => $this->examIndication,
                'exams' => $this->analysesRetenues(),
            ],
            Service::EXAM_IMAGING => [
                'modality' => $this->examModality,
                'body_site' => $this->examBodySite,
                'requested_at' => now()->toDateTimeString(),
                'priority' => $this->examPriority,
                'indication' => $this->examIndication,
            ],
            default => null,
        };
    }

    /**
     * Une demande d'analyses sans aucune analyse ne veut rien dire. Le
     * controle porte sur l'ensemble des lignes et non sur l'une d'elles : il
     * ne peut donc pas vivre dans les regles.
     */
    protected function examinationIsComplete(?Service $toService): bool
    {
        if ($toService?->examKind() !== Service::EXAM_LABORATORY) {
            return true;
        }

        if ($this->analysesRetenues() !== []) {
            return true;
        }

        $this->addError('examExams', 'Indiquez au moins une analyse.');

        return false;
    }

    /**
     * La capacite qu'exige la demande adressee a ce service, ou nulle quand il
     * n'en realise pas.
     */
    protected function examinationCapability(?Service $toService): ?string
    {
        return match ($toService?->examKind()) {
            Service::EXAM_LABORATORY => StaffType::CAP_ORDER_LABORATORY,
            Service::EXAM_IMAGING => StaffType::CAP_ORDER_IMAGING,
            default => null,
        };
    }

    /**
     * @return array<int, array{name: string, category: string}>
     */
    private function analysesRetenues(): array
    {
        return array_values(array_filter(
            $this->examExams,
            static fn ($ligne) => filled($ligne['name'] ?? null),
        ));
    }
}
